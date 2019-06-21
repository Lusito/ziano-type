<?php namespace Lusito\ZianoType;

class TemplateGenerator
{
    private $plainEcho = "";
    public function run($tree, $filename, $useInstructions, $doctype)
    {
        $this->plainEcho = "";
        ob_start();
        echo "<?php ";
        if (!empty($useInstructions)) {
            echo "\n";
            foreach ($useInstructions as $useInstruction)
                echo "use $useInstruction;\n";
        }
        echo "return function(\$zRenderer, \$zProps, \$innerHTML='') {\n";
        echo "    extract(\$zProps);\n";
        echo "    unset(\$zProps);\n";
        if ($doctype)
            echo "    echo '$doctype';\n";
        $this->generateCode($tree, '    ');
        $this->finishEchoPlain();
        echo "};";
        return ob_get_clean();
    }

    private function echoPlain($value, $indentation)
    {
        $value = str_replace(["\r", "\n"], ["", "\\n"], $value);
        if (empty($this->plainEcho))
            $this->plainEcho = "{$indentation}echo \"$value";
        else
            $this->plainEcho .= $value;
    }

    private function finishEchoPlain()
    {
        if (!empty($this->plainEcho)) {
            echo $this->plainEcho . "\";\n";
            $this->plainEcho = "";
        }
    }

    private function generateCode($children, $indentation)
    {
        foreach ($children as $child) {
            $type = $child['type'];
            if ($type === 'text') {
                $text = $child['text'];
                if (Utils::maybeContainsCode($text)) {
                    $this->finishEchoPlain();
                    $code = Utils::prepareCodeParts($text);
                    echo "{$indentation}echo \$zRenderer->escapeText($code);\n";
                } else if (!empty($text)) {
                    $this->echoPlain(Utils::safeText(Utils::escapeText($text)), $indentation);
                }
            } else if ($type === 'if' || $type === 'else-if') {
                $this->finishEchoPlain();
                $if = $type === 'if' ? 'if' : 'else if';
                echo "{$indentation}  $if ({$child['condition']}) {\n";
                $this->generateCode($child['children'], $indentation . '    ');
                $this->finishEchoPlain();
                echo "{$indentation}}\n";
            } else if ($type === 'else') {
                echo "{$indentation}else {\n";
                $this->generateCode($child['children'], $indentation . '    ');
                $this->finishEchoPlain();
                echo "{$indentation}}\n";
            } else if ($type === 'for-each') {
                $this->finishEchoPlain();
                echo "{$indentation}foreach({$child['expression']}) {\n";
                $this->generateCode($child['children'], $indentation . '    ');
                $this->finishEchoPlain();
                echo "{$indentation}}\n";
            } else if ($type === 'include') {
                $this->finishEchoPlain();
                echo "{$indentation}ob_start();\n";
                $this->generateCode($child['children'], $indentation);

                $props = [];
                foreach ($child['props'] as $key => $value)
                    $props[] = "'$key' => " . Utils::exportPropertyValue($value);
                $props = implode(", ", $props);
                $filename = $child['filename'];
                $this->finishEchoPlain();
                echo "{$indentation}\$zRenderer->render(\"$filename\", [{$props}], ob_get_clean());\n";
            } else if ($type === 'render') {
                $this->finishEchoPlain();
                $content = $child['content'];
                if ($content === 'innerHTML')
                    echo "{$indentation}echo \$innerHTML;\n";
                else if ($content === 'scripts')
                    echo "{$indentation}\$zRenderer->renderScripts();\n";
                else if ($content === 'stylesheets')
                    echo "{$indentation}\$zRenderer->renderStylesheets();\n";
                else
                    throw new \Exception("Unknown render type: $content");
            } else if ($type === 'raw') {
                $this->finishEchoPlain();
                $text = addcslashes($child['content'], '"');
                if (Utils::maybeContainsCode($text)) {
                    $code = Utils::prepareCodeParts($text);
                    echo "{$indentation}echo $code;\n";
                } else {
                    echo "{$indentation}echo \"" . $text . "\";\n";
                }
            } else {
                $usedVars = [];
                $this->openTag($child['tag'], $child['props'], $indentation, $usedVars);
                if (!Utils::isSelfClosingTag($child['tag'])) {
                    $this->generateCode($child['children'], $indentation);
                    $this->closeTag($child['tag'], $indentation);
                } else if (!empty($child['children'])) {
                    throw new Error('Self closing tags may not contain children');
                }
            }
        }
    }

    private function openTag($tag, $props, $indentation)
    {
        $this->echoPlain("<$tag", $indentation);
        foreach ($props as $key => $value) {
            if ($value === null)
                continue;
            if ($key === 'z-extract') {
                $this->finishEchoPlain();
                echo "{$indentation}foreach($value as \$key => \$value) {\n";
                echo "{$indentation}    echo \" \$key=\\\"\";\n";
                echo "{$indentation}    echo \$zRenderer->escapeProperty(\$value);\n";
                echo "{$indentation}    echo '\"';\n";
                echo "{$indentation}}\n";
            } else if (!Utils::isSingleProperty($key)) {
                $this->echoPlain(" $key=\\\"", $indentation);
                if (Utils::maybeContainsCode($value)) {
                    $code = Utils::prepareCodeParts($value);
                    $this->finishEchoPlain();
                    echo "{$indentation}echo \$zRenderer->escapeProperty($code);\n";
                } else {
                    $this->echoPlain(Utils::escapeProperty($value), $indentation);
                }
                $this->echoPlain('\"', $indentation);
            } else if ($value === true) {
                $this->echoPlain(" $key", $indentation);
            }
        }

        $this->echoPlain(">", $indentation);
    }

    private function closeTag($tag, $indentation)
    {
        $this->echoPlain("</$tag>", $indentation);
    }
}
