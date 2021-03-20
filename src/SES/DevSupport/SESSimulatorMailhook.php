<?php

namespace Ingenerator\SwiftMailer\SES\DevSupport;


use Ingenerator\Mailhook\Mailhook;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use RuntimeException;
use function array_filter;
use function basename;
use function copy;
use function file_get_contents;
use function glob;
use function is_dir;
use function mkdir;
use function unlink;

class SESSimulatorMailhook extends Mailhook
{
    protected string $dump_dir;

    public function __construct(string $dump_dir)
    {
        $this->dump_dir = $dump_dir;
        $this->parser   = new SESSimulatorEmailParser;
    }

    public function copyFilesTo(string $dir): void
    {
        if ( ! is_dir($dir)) {
            mkdir($dir, 0777, TRUE);
        }

        foreach ($this->listAllFilesInDumpDir() as $file) {
            copy($file, $dir.'/'.basename($file));
        }
    }

    public function purge(): void
    {
        foreach ($this->listAllFilesInDumpDir() as $file) {
            if ( ! unlink($file)) {
                throw new RuntimeException('Could not delete mail dump at '.$file);
            }
        }
    }

    public function refresh(): void
    {
        $mails     = $this->listAllMailJSONsInDumpDir();
        $new_mails = array_filter($mails, function ($file) { return ! isset($this->emails[$file]); });
        foreach ($new_mails as $file) {
            $this->emails[$file] = $this->parser->parseSimulatorCapture(JSON::decodeArray(file_get_contents($file)));
        }
    }

    protected function listAllFilesInDumpDir(): array
    {
        if ( ! is_dir($this->dump_dir)) {
            return [];
        }

        return glob($this->dump_dir.'/*') ?: [];
    }

    protected function listAllMailJSONsInDumpDir(): array
    {
        if ( ! is_dir($this->dump_dir)) {
            return [];
        }

        return glob($this->dump_dir.'/*.email.json') ?: [];
    }

}
