<?php

namespace App\Modules\WorkOrder\Processors\Helpers;

use Illuminate\Contracts\Container\Container;

class RossStorePdfExtractor
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Get content from given PDF file
     *
     * @param $pdfFilePath
     *
     * @return string
     */
    public function getContent($pdfFilePath)
    {
        $tmpDir = $this->app->config->get('app.tmp_dir');
        $pdfToHtml = $this->app->config->get('app.commands.pdftohtml');

        $txtFile = tempnam($tmpDir, 'pdf_');
        $cmd = "cd {$tmpDir}/; {$pdfToHtml} -i -xml {$pdfFilePath} {$txtFile}";
        $content = '';
        system($cmd);

        $txt = file_get_contents($txtFile . '.xml');
        if ($txt !== false) {
            $startPos = stripos($txt, '<text');
            $txt = substr($txt, $startPos);
            $txt = str_replace(['</page>', '</pdf2xml>'], '', $txt);
            $textArray = explode('</text>', $txt);
            natsort($textArray);

            $content = implode('', $textArray);
            $content = strip_tags($content);
        }

        return $content;
    }
}
