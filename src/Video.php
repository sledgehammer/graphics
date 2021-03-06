<?php

namespace Sledgehammer\Graphics;

use Exception;

/**
 * Uses ffmpeg to extract frames, or to convert a video.
 *
 *
 * @property-read float $duration in seconds.
 * @property-read int $width in pixels.
 * @property-read int $height in pixels.
 */
class Video extends Image
{
    /**
     * Full path of the ffmpeg executable.
     *
     * @var string
     */
    public static $ffmpeg = null;

    /**
     * The positon of the current frame (in sec).
     *
     * @var float
     */
    public $position = 0;

    /**
     * Encoder & Decoder notices/warnings.
     *
     * @var array
     */
    public $notices = [];

    /**
     * Parameters that are placed before the "-i" to speficy the input format & codec.
     *
     * @var array|string
     **/
    private $inputParameters;

    /**
     * The duration, width and heigth properties.
     *
     * @var array
     */
    private $properties;

    // Parser properties

    /**
     * Errors reported by ffmpeg.
     *
     * @var array
     */
    private $errors;

    /**
     * Parser state.
     *
     * @var enum
     */
    private $state;

    /**
     * Timestamp.
     *
     * @var int
     */
    private $started;

    /**
     * Filename of the progress json file.
     *
     * @var string
     */
    private $progressFile;

    /**
     * Constructor.
     *
     * @param string       $filename
     * @param string|array $inputParameters Parameters that are placed before the "-i" to speficy the input format & codec.
     */
    public function __construct($filename, $inputParameters = '')
    {
        if (file_exists($filename) === false && preg_match('/%|^concat:/', $filename) === false) {
            throw new Exception('File "'.$filename.'" not found');
        }
        $this->filename = $filename;
        $this->inputParameters = $inputParameters;
    }

    public function rasterize()
    {
        if ($this->gd === null) {
            $imageFile = TMP_DIR.uniqid('frame').'.png';
            $parameters = [
                'vframes' => 1,
            ];
            $inputParameters = [];
            if ($this->position !== 0) {
                $inputParameters['ss'] = $this->position;
            }
            $this->process($imageFile, $parameters, $inputParameters);
            $videoFile = $this->filename;
            $this->filename = $imageFile;
            $this->gd = parent::rasterize();
            unlink($imageFile);
            $this->filename = $videoFile;
        }

        return $this->gd;
    }

    /**
     * Save frame, video or audio.
     *
     * @param string $filename The target filename
     * @param array  $options
     */
    public function saveAs($filename, $options = [])
    {
        if ($filename === null || substr(mimetype($filename, true), 0, 6) === 'image/') {
            return parent::saveAs($filename, $options);
        }
        $this->process($filename, $options);
    }

    /**
     * Process the video with ffmpeg.
     *
     * @param string       $outputFile       The output filename
     * @param string|array $outputParameters
     * @param string|array $inputParameters  Additional parameters that are placed before the "-i"
     *
     * @throws Exceptions
     */
    public function process($outputFile = null, $outputParameters = [], $inputParameters = [])
    {
        if (self::$ffmpeg === null) {
            self::$ffmpeg = trim(shell_exec('which ffmpeg')); // find ffmpeg in the PATH
            if (self::$ffmpeg === '') {
                $location = '/usr/local/bin/ffmpeg';
                if (file_exists($location)) {
                    self::$ffmpeg = $location;
                } else {
                    throw new Exception('ffmpeg not found');
                }
            }
        }
        // Build ffmpeg command.
        $command = self::$ffmpeg;
        if (is_string($this->inputParameters)) {
            $command .= ' '.$this->inputParameters;
        } else {
            foreach ($this->inputParameters as $parameter => $value) {
                $command .= ' -'.$parameter.' '.$value;
            }
        }
        if (is_string($inputParameters)) {
            $command .= ' '.$inputParameters;
        } else {
            foreach ($inputParameters as $parameter => $value) {
                $command .= ' -'.$parameter.' '.$value;
            }
        }
        $command .= ' -i '.escapeshellarg($this->filename);
        if (is_string($outputParameters)) {
            $command .= ' '.$outputParameters;
        } else {
            foreach ($outputParameters as $parameter => $value) {
                $command .= ' -'.$parameter.' '.$value;
            }
        }

        if ($outputFile !== null) {
            $command .= ' "'.$outputFile.'"';
            $this->progressFile = $outputFile.'-progress.json';
            file_put_contents($this->progressFile, json_encode(['percentage' => 0, 'seconds_remaining' => 'UNKNOWN']));
        } else {
            $this->progressFile = false;
        }
        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin is a pipe that the child will read from
            1 => ['pipe', 'w'], // stdout is a pipe that the child will write to
            2 => ['pipe', 'w'],   // stderr is a pipe that the child will write to
        ];

        set_time_limit(0);
        $process = proc_open($command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new Exception('Command: "" failed');
        }
        fclose($pipes[0]);
        fclose($pipes[1]);

        $stderr = $pipes[2];
        // reset parser
        $this->errors = [];
        $this->properties = [];
        $output_buffer = '';
        $this->state = 'VERSION_INFO';
        $this->started = microtime(true);

        while (!feof($stderr)) {
            $part = fgets($stderr, 10); // na elke N karakters de output parsen
            // De endline opsporen
            $endline = strpos($part, "\n");
            if ($endline === false) {
                $endline = strpos($part, "\r");
            }
            if ($endline === false) { // Er zat geen regeleinde in de buffer
                $output_buffer .= $part;
                continue; // volgende 5 bytes inlezen
            }
            // Er is een gehele regel opgespaard
            $line = $output_buffer.substr($part, 0, $endline);
            $output_buffer = substr($part, $endline + 1);
            $this->parseLine($line);
        }
        fclose($stderr);

        // It is important that you close any pipes before calling
        // proc_close in order to avoid a deadlock
        $return_value = proc_close($process);
        if ($this->progressFile) {
            unlink($this->progressFile);
        }
        if (count($this->errors) != 0) {
            if ($outputFile === null && count($this->errors) == 1 && $this->errors[0] == 'At least one output file must be specified') {
                return;
            }
            if ($return_value != 0) {
                throw new Exception('FFMpeg: "'.implode("\n", $this->errors).'" CMD: '.$command);
            }
            foreach ($this->errors as $message) {
                if ($message != '') {
                    $this->notices[] = ['message' => $message, 'command' => $command];
                }
            }
        }
    }

    private function parseLine($line)
    {
        //		echo $this->state.'| '.$line."\n";

        switch ($this->state) {

            case 'VERSION_INFO':
                if (preg_match('/^ffmpeg version /', $line)) {
                    // first line
                } elseif ($line == '') { // Aan het einde van het versieinfo blok is een wit-regel
                    $this->state = 'INFO_ENDED';
                } elseif (substr($line, 0, 7) == 'Input #') { // Deze keer geen wit-regel
                    $this->state = 'INFO_ENDED';

                    return $this->parseLine($line); // reparse
                } elseif (substr($line, 0, 2) != '  ') {
                    $this->parseError($line);
                    $this->state = 'INFO_ENDED';
                }
                break;

            case 'INFO_ENDED':
                if (substr($line, 0, 7) == 'Input #') {
                    $this->state = 'INPUT_FILE_INFO';
                } elseif (substr($line, 0, 8) == 'Output #') {
                    $this->state = 'IGNORE_INFO_BLOCK';
                } elseif ($line == 'Stream mapping:') {
                    $this->state = 'IGNORE_INFO_BLOCK';
                } elseif (substr($line, 0, 17) == 'Press [q] to stop') {
                    $this->state = 'PROGRESS';
                } else {
                    $this->parseError($line);
                }
                break;

            case 'INPUT_FILE_INFO':
                if (substr($line, 0, 2) != '  ') { // Hoort deze regel niet bij de Input info
                    $this->state = 'INFO_ENDED';

                    return $this->parseLine($line); // reparse
                }
                // Deze regel bevat info
                if (preg_match('/Duration/', $line)) { // duration info?
                    $duration = substr($line, strlen('  Duration: '), strlen('HH:MM:SS.ms'));
                    preg_match('/(^[0-9:]+)(.*$)/', $duration, $duration_split); // Splits de duration op in een H:i:s (1) en de miliseconden (2)
                    $reference_time = strtotime('1970-01-02 00:00:00');
                    $movie_time = strtotime('1970-01-02 '.$duration_split[1]);
                    $this->properties['duration'] = (float) ($movie_time - $reference_time).$duration_split[2];
                } elseif (preg_match('/(Video: )(.*)/', $line, $match)) { // video info
                    $info = $match[2];
                    //$this->properties['video_format'] = substr($info, 0, strpos($info, ',')); // codec: mpeg, divx enz
                    preg_match('/, [0-9]+x[0-9]+/', $info, $match);
                    $resolution = explode('x', substr($match[0], 2));
                    $this->properties['width'] = $resolution[0];
                    $this->properties['height'] = $resolution[1];
                }
                    // Ander kanaal dat audio of andere info toont
                
                break;

            // Ingesprongen informatie (Zoals "Output #" & "Stream mapping") negeren
            case 'IGNORE_INFO_BLOCK':
                if (substr($line, 0, 2) != '  ') { // Hoort deze regel niet bij het block
                    $this->state = 'INFO_ENDED';

                    return $this->parseLine($line);
                }
                break;

            // Voortgangs informatie
            case 'PROGRESS':
                preg_match('/(time=)([0-9:.]+)/', $line, $match);
                if (count($match)) { // Voortgangs info?
                    if ($this->progressFile) {
                        $time_completed = explode(':', $match[2]);
                        $seconds_completed = ($time_completed[0] * 3600) + ($time_completed[1] * 60) + $time_completed[2];
                        if ($seconds_completed != 0) {
                            $completed = $seconds_completed / $this->properties['duration'];
                            $seconds_elapsed = microtime(true) - $this->started;
                            $seconds_remaining = ($seconds_elapsed / $completed) - $seconds_elapsed;
                            if ($completed > 1) { // Als de duration niet klopte. (deze was korter dan de daadwerkelijke lengte)
                                $completed = 1; // naar beneden afronden (100%)
                                $seconds_remaining = 0; // naar boven afronden (0) ipv negatief
                            }
                            file_put_contents($this->progressFile, json_encode(['percentage' => round($completed * 100, 1), 'seconds_remaining' => ceil($seconds_remaining)])); // Write progress to file
//							echo "\r".round($completed * 100)."%   ".round($seconds_remaining)." sec remaining";
                        }
                    }
                } elseif (preg_match('/(video:[0-9]+kB)|(audio:[0-9]+kB)/', $line)) { // summary?
                    $this->state = 'FINISHED';
                } else {
                    $this->parseError($line);
                }
                break;

            case 'FINISHED':
                $this->parseError($line);
                break;
        }
    }

    private function parseError($line)
    {
        // Filter harmless errors
        if (preg_match('/Seems stream [0-9]+ codec frame rate differs from container frame rate: 1000\.00/', $line)) {
            return;
        }
        // Spaar de errors op
        // echo '### '.$line."\n";
        $this->errors[] = $line;
    }

    /**
     * Vraag gegevens op van het filmbestand
     * (via de property).
     */
    public function __get($property)
    {
        if (count($this->properties) == 0) {
            try {
                $this->process(null); // run ffmpeg, zodat de properties array gevuld word.
            } catch (Exception $Exception) {
                if (count($this->properties) < 3) { // Zijn de eigenschappen nog steeds niet bekend?
                    throw $Exception;
                }
            }
        }
        if (isset($this->properties[$property])) {
            return $this->properties[$property];
        }

        return parent::__get($property);
    }
}
