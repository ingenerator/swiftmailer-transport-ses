<?php

namespace Ingenerator\SwiftMailer\SES\DevSupport;


use Ingenerator\Mailhook\Email;
use Ingenerator\Mailhook\EmailParser;
use UnexpectedValueException;
use function array_merge;
use function array_pop;
use function preg_match;
use function str_replace;

class SESSimulatorEmailParser extends EmailParser
{
    /**
     * @deprecated Use parseSimulatorCapture
     * @param string $raw_message
     *
     * @return Email
     */
    public function parse($raw_message): Email
    {
        // this method expects $raw_message to be a string of a single message body
        // which doesn't work in this case of an array needing parsed to assert correct action has been called
        throw new \BadMethodCallException('You probably mean to call parseSimulatorCapture');
    }

    public function parseSimulatorCapture(array $simulator_capture): Email
    {
        if ($simulator_capture['post']['Action'] !== 'SendRawEmail') {
            throw new UnexpectedValueException(
                'Expected AWS action SendRawEmail, got '.$simulator_capture['post']['Action']
            );
        }

        $mime_message = $simulator_capture['raw_data'];
        // Seems Swift renders with a windows newline, doesn't trouble SES but bothers mailhook's old parser
        $mime_message = str_replace("\r\n", "\n", $mime_message);

        // Swift also wraps the headers to multiple lines and that also bothers mailhook's very brittle parser
        $mime_message = $this->unWrapHeaderLines($mime_message);

        return parent::parse($mime_message);
    }

    private function unWrapHeaderLines(string $mime_message): string
    {
        /*
         A wrapped header looks like this (pipes added to show the alignment)
            |Date: Thu, 30 Jul 2020 22:55:04 +0100
            |Subject: inGenerator - Account Activation
            |From: inGenerator <noreply@ingenerator.com>
            |To: GPva067Zj sCADSouhPQ4vatf
            | <test+GPva067ZjsCADSouhPQ4vatf.test@ingenerator.com>
            |MIME-Version: 1.0
        */
        $original_lines = explode("\n", $mime_message);
        $fixed_lines    = [];
        while (count($original_lines)) {
            $line = array_shift($original_lines);

            if (trim($line) === '') {
                // Header boundary, stop
                $fixed_lines[] = $line;
                break;
            }

            if (preg_match('/^[^\s]+?: /', $line)) {
                // New header, it's OK
                $fixed_lines[] = $line;
            } else {
                // Wrapped line, merge it up
                $fixed_lines[] = array_pop($fixed_lines).$line;
            }
        }

        $all_lines = array_merge($fixed_lines, $original_lines);

        return implode("\n", $all_lines);
    }

}
