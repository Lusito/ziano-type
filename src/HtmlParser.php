<?php namespace Lusito\ZianoType;

class HtmlNode
{
    public $parent;
    public $type;
    public $pos;
    public $name;
    public $props = [];
    public $children = [];

    public function __construct($parent, $type, $pos)
    {
        $this->parent = $parent;
        $this->type = $type;
        $this->pos = $pos;
    }

    public function hasProp($name)
    {
        return isset($this->props[$name]);
    }
}

class HtmlParser
{
    private $filename;
    private $content;
    private $length;
    private $offset;
    private $line;
    private $col;
    private $rootNode;
    private $node;

    public function parse($filename)
    {
        $this->filename = $filename;
        $this->content = file_get_contents($filename);
        $this->length = strlen($this->content);
        $this->offset = 0;
        $this->line = 1;
        $this->col = 1;
        $this->rootNode = $this->node = new HtmlNode(null, 'root', [1, 1]);
        while (!$this->done())
            $this->parseElement();
        return $this->rootNode->children;
    }

    private function done()
    {
        return $this->offset >= $this->length;
    }

    private function parseElement()
    {
        $this->parseWhitespaceAndComments();
        if ($this->done())
            return;

        $pos = $this->getPos();
        if (!$this->test("<")) {
            $end = strpos($this->content, "<", $this->offset);
            if ($end === false)
                $end = $this->length;
            $text = $this->untilPos($end);
            $this->increaseOffset($text);
            $node = $this->createNode("text", $pos, false);
            $node->children[] = $text;
            return;
        }

        if ($this->test("/")) {
            $this->expect("{$this->node->name}>");
            $this->node = $this->node->parent;
        } else if ($this->test("!")) {
            if ($this->node !== $this->rootNode || !empty($this->node->children))
                $this->error("Doctype may only be specified at the beginning of a template", 5, $pos);
            else {
                $end = strpos($this->content, ">", $this->offset);
                $content = $this->untilPos($end + 1);
                $node = $this->createNode("doctype", $pos, false);
                $this->increaseOffset($content, 1);
                $node->children[] = "<!" . $content;
            }
        } else {
            $end = $this->strposRegex("/(\s|\/|>)/", $this->offset);
            if ($end === false)
                $this->error("Expected matching > for tag", 5, $pos);
            $tagName = substr($this->content, $this->offset, $end - $this->offset);
            $this->increaseOffset($tagName);
            $node = $this->createNode("tag", $pos, true);
            $open = $this->parseProps($pos, strlen($tagName) + 1);
            if ($open)
                $open = !Utils::isSelfClosingTag($tagName);
            $node->name = $tagName;
            if (!$open)
                $this->node = $node->parent;
        }
    }

    private function parseProps($pos, $lookAhead)
    {
        while (!$this->done()) {
            $this->parseWhitespace(false);
            if ($this->test("/>"))
                return false;
            if ($this->test(">"))
                return true;

            $end = $this->strposRegex("/( |=|>)/", $this->offset);
            $name = $this->untilPos($end);
            $this->increaseOffset($name);
            if ($this->test('='))
                $this->node->props[$name] = $this->parsePropValue($name);
            else
                $this->node->props[$name] = true;
        }
        $this->error("Unexpected end of file reading properties", $lookAhead, $pos);
    }

    private function parsePropValue($name)
    {
        if ($this->test('"')) {
            $end = strpos($this->content, '"', $this->offset);
            if ($end === false)
                $this->error("Error looking for closing '\"' after property '$name' value", 10, $this->getPos());
            $value = $this->untilPos($end);
            $this->increaseOffset($value, 1);
            $value = htmlspecialchars_decode($value, ENT_COMPAT | ENT_HTML5);
            return $value;
        }
        $this->error("Error looking for '\" after property name '$name'", 10, $this->getPos());
    }

    private function untilPos($end)
    {
        return substr($this->content, $this->offset, $end - $this->offset);
    }

    private function strposRegex($regex, $offset)
    {
        if (preg_match($regex, $this->content, $matches, PREG_OFFSET_CAPTURE, $offset))
            return $matches[0][1];
        return false;
    }

    private function error($message, $lookAhead, $pos)
    {
        $near = substr($this->content, $this->offset, $lookAhead);
        throw new \Exception("Error: $message, at {$this->filename}:{$pos[0]}:{$pos[1]}, near $near");
    }

    private function createNode($type, $pos, $open)
    {
        $node = new HtmlNode($this->node, $type, $pos);
        $this->node->children[] = $node;
        if ($open)
            $this->node = $node;
        return $node;
    }

    private function parseWhitespace($createNode)
    {
        $pos = $this->getPos();
        $hadWhitespace = false;
        for ($i = $this->offset; $i < $this->length; $i++) {
            $c = $this->content[$i];
            if ($c === " " || $c === "\t" || $c === "\r") {
                $this->offset++;
                $this->col++;
                $hadWhitespace = true;
            } else if ($c === "\n") {
                $this->offset++;
                $this->line++;
                $this->col = 1;
                $hadWhitespace = true;
            } else {
                break;
            }
        }
        if ($hadWhitespace && $createNode) {
            $node = $this->createNode('text', $pos, false);
            $node->children[] = ' ';
        }
        return $hadWhitespace;
    }

    private function increaseOffset($skippedText, $extraCols = 0)
    {
        $len = strlen($skippedText);
        for ($i = 0; $i < $len; $i++) {
            $c = $skippedText[$i];
            if ($c === "\n") {
                $this->line++;
                $this->col = 1;
            } else {
                $this->col++;
            }
        }
        $this->col += $extraCols;
        $this->offset += $len + $extraCols;
    }

    private function skipComment()
    {
        $pos = $this->getPos();
        if ($this->test("<!--")) {
            $end = strpos($this->content, "-->", $this->offset);
            if ($end === false)
                $this->error("Expected end of comment", 10, $this->getPos());

            $comment = $this->untilPos($end);
            $this->increaseOffset($comment, 3);
        }
        return false;
    }

    private function parseWhitespaceAndComments()
    {
        while ($this->parseWhitespace(true) || $this->skipComment())
            continue;
    }

    private function expect($part)
    {
        if (!$this->test($part))
            $this->error("Expected '$part'", strlen($part), $this->getPos());
    }

    private function test($part)
    {
        $len = strlen($part);
        $result = substr($this->content, $this->offset, $len) === $part;
        if ($result)
            $this->increaseOffset($part);
        return $result;
    }

    private function getPos()
    {
        return [$this->line, $this->col];
    }
}
