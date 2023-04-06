<?php

namespace Icinga\Module\Mibs;

use Icinga\Exception\IcingaException;
use React\ChildProcess\Process;
use React\EventLoop\Factory as Loop;
use RuntimeException;

class MibParser
{
    protected static $lastValidationErrors;

    public static function preValidateFile($filename)
    {
        $binary = '/usr/bin/smilint';
        if (! file_exists($binary)) {
            throw new RuntimeException('%s not found', $binary);
        }

        $command = sprintf(
            "exec %s %s -l 2",
            $binary,
            escapeshellarg($filename)
        );

        $loop = Loop::create();
        $process = new Process($command);
        $process->start($loop);
        $buffer = '';
        $timer = $loop->addTimer(10, function () use ($process) {
            $process->terminate();
        });
        $process->stdout->on('data', function ($string) use (& $buffer) {
            $buffer .= $string;
        });
        $process->stderr->on('data', function ($string) use (& $buffer) {
            $buffer .= $string;
        });
        $process->on('exit', function ($exitCode, $termSignal) use ($timer, $loop) {
            $loop->cancelTimer($timer);
            if ($exitCode === null) {
                if ($termSignal === null) {
                    throw new IcingaException(
                        'Fuck, I have no idea how the validator got killed'
                    );
                } else {
                    throw new IcingaException(
                        "They killed the validator with $termSignal"
                    );
                }
            } else {
                if ($exitCode !== 0) {
                    throw new IcingaException("Validator exited with $exitCode");
                }
            }
            $loop->stop();
        });

        $loop->run();

        if (empty($buffer)) {
            return true;
        } else {
            self::$lastValidationErrors = $buffer;

            return false;
        }
    }

    public static function getLastValidationError()
    {
        return self::$lastValidationErrors;
    }

    public static function parseString($string)
    {
        $loop = Loop::create();
        $process = new Process(static::getCommandString());
        $process->start($loop);
        $buffer = '';
        $timer = $loop->addTimer(10, function () use ($process) {
            $process->terminate();
        });
        $process->stdout->on('data', function ($string) use (& $buffer) {
            $buffer .= $string;
        });
        $errBuffer = '';
        $process->stderr->on('data', function ($string) use (& $errBuffer) {
            $errBuffer .= $string;
        });
        $process->on('exit', function ($exitCode, $termSignal) use (&$buffer, &$errBuffer, $timer, $loop) {
            $loop->cancelTimer($timer);
            $out = [];
            if (! empty($buffer)) {
                $out[] = "STDOUT: $buffer";
            }
            if (! empty($errBuffer)) {
                $out[] = "STDERR: $errBuffer";
            }
            if (empty($out)) {
                $out = '';
            } else {
                $out = ': ' . implode(', ', $out);
            }
            $loop->stop();
            if ($exitCode === null) {
                if ($termSignal === null) {
                    throw new IcingaException(
                        'Fuck, I have no idea how the parser got killed'
                    );
                } else {
                    throw new IcingaException(
                        "They killed the parser with $termSignal$out"
                    );
                }
            } else {
                if ($exitCode !== 0) {
                    throw new IcingaException("Parser exited with $exitCode$out");
                }
            }
        });

        $process->stdin->write("$string\n");

        $loop->run();
        return json_decode($buffer);
    }

    protected static function getCommandString()
    {
        return 'exec ' . dirname(dirname(__DIR__)) . '/contrib/mib-parser.pl';
    }

    public static function parseFile($file)
    {
        return static::parseString(file_get_contents($file));
    }
}
