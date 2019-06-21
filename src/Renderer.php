<?php namespace Lusito\ZianoType;

class Renderer
{
    private $themes;
    private $cachePath;
    private $templateParser;
    private $templateGenerator;
    private $scripts;
    private $stylesheets;

    public function __construct($config)
    {
        $this->scripts = $config['scripts'];
        $this->stylesheets = $config['stylesheets'];
        $this->themes = $config['themes'];
        $this->cachePath = $config['cachePath'];
        $this->templateParser = new TemplateParser();
        $this->templateGenerator = new TemplateGenerator();
    }

    public function render($filename, $props, $innerHTML = '', $return = false)
    {
        try {
            ob_start();
            $fn = $this->getTemplateFn($filename);
            $fn($this, $props, $innerHTML);
            return $return ? ob_get_clean() : ob_end_flush();
        } catch (\Throwable $e) {
            // fixme: notify caller about this exception as well
            ob_clean();
            $errorMessage = $e->getMessage();
            $msg = $this->formatError("Error: Could not render $filename correctly: $errorMessage");
            if ($return)
                return $msg;

            echo $msg;
            return false;
        }
    }

    private function formatError(string $message)
    {
        return '<p>' . Utils::escapeText($message) . '</p>';
    }

    private function getTemplateFn($filename)
    {
        foreach ($this->themes as $theme) {
            list($themeName, $themePath) = $theme;
            $filePath = "$themePath/$filename";
            if (file_exists($filePath)) {
                $cacheFilePath = "{$this->cachePath}/$themeName/$filename.php";
                return $this->getTemplateFnTheme($filePath, $cacheFilePath);
            }
        }
        return function ($zRenderer, $zProps, $innerHTML = '') use ($filename) {
            echo $this->formatError("Error: Could not find $filename");
        };
    }

    private function getTemplateFnTheme($filePath, $cacheFilePath)
    {
        if (!file_exists($cacheFilePath) || filemtime($cacheFilePath) < filemtime($filePath)) {
            if (!file_exists($filePath))
                throw new \Error("$filePath doesn't exist");

            try {
                $tree = $this->templateParser->parse($filePath);
                $code = $this->templateGenerator->run($tree, $filePath, $this->templateParser->useInstructions, $this->templateParser->doctype);
            } catch (\Throwable $e) {
                // fixme: notify caller about this exception as well
                return function ($zRenderer, $zProps, $innerHTML = '') use ($filePath, $e) {
                    $errorMessage = $e->getMessage();
                    echo $this->formatError("Error: Could not parse $filePath correctly: $errorMessage");
                };
            }
            $cacheDir = dirname($cacheFilePath);
            if (!is_dir($cacheDir))
                mkdir($cacheDir, 0777, true);
            file_put_contents($cacheFilePath, $code);
        }
        $fn = include($cacheFilePath);
        return $fn;
    }

    // fixme: call utils directly?
    public function escapeText($text)
    {
        return Utils::escapeText($text);
    }

    // fixme: call utils directly?
    public function escapeProperty($text)
    {
        return Utils::escapeProperty($text);
    }

    public function renderScripts()
    {
        foreach ($this->scripts as $script) {
            $script = Utils::safeString(Utils::escapeProperty($script));
            echo "<script src=$script></script>";
        }
    }

    public function renderStylesheets()
    {
        foreach ($this->stylesheets as $stylesheet) {
            $stylesheet = Utils::safeString(Utils::escapeProperty($stylesheet));
            echo "<link href=$stylesheet type=\"text/css\" rel=\"stylesheet\">";
        }
    }
}
