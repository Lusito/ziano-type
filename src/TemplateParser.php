<?php namespace Lusito\ZianoType;

class TemplateParser
{
    private $htmlParser;
    public $doctype;
    public $useInstructions;

    public function __construct()
    {
        $this->htmlParser = new HtmlParser();
    }

    public function parse($filename)
    {
        $children = $this->htmlParser->parse($filename);
        $this->useInstructions = [];
        $this->doctype = null;
        return $this->childrenToArray($children);
    }

    private function createIf($child)
    {
        return [
            'expression' => true,
            'type' => 'if',
            'condition' => $child->props['if'],
            'children' => $this->childrenToArray($child->children)
        ];
    }

    private function createElseIf($child, $type)
    {
        $entry = [
            'expression' => true,
            'type' => $type,
            'children' => $this->childrenToArray($child->children)
        ];
        if ($type === 'else-if')
            $entry['condition'] = $child->props['else-if'];
        return $entry;
    }

    private function createForEach($child)
    {
        return [
            'expression' => true,
            'type' => 'for-each',
            'expression' => $child->props['for-each'],
            'children' => $this->childrenToArray($child->children)
        ];
    }

    private function createInclude($child)
    {
        $filename = $child->props['include'];
        unset($child->props['include']);
        return [
            'expression' => true,
            'type' => 'include',
            'filename' => $filename,
            'props' => $child->props,
            'children' => $this->childrenToArray($child->children)
        ];
    }

    private function createRender($child)
    {
        return [
            'expression' => true,
            'type' => 'render',
            'content' => $child->props['render']
        ];
    }

    private function createRaw($child)
    {
        return [
            'expression' => true,
            'type' => 'raw',
            'content' => $child->props['raw']
        ];
    }

    private function childrenToArray($children)
    {
        $array = [];
        foreach ($children as $child) {
            if ($child->type === 'tag') {
                if ($child->name === 'z') {
                    $end = end($array);
                    if ($end && $end['type'] === 'whitespace')
                        array_pop($array);

                    if ($child->hasProp('if')) {
                        if (!empty($child->children))
                            $array[] = $this->createIf($child);
                    } else if ($child->hasProp('else-if') || $child->hasProp('else')) {
                        $type = $child->hasProp('else-if') ? 'else-if' : 'else';
                        if (!$end || ($end['type'] !== 'if' && $end['type'] !== 'else-if'))
                            throw new \Exception("$type must follow if or else-if");
                        if (!empty($child->children))
                            $array[] = $this->createElseIf($child, $type);
                    } else if ($child->hasProp('for-each')) {
                        if (!empty($child->children))
                            $array[] = $this->createForEach($child);
                    } else if ($child->hasProp('include')) {
                        $array[] = $this->createInclude($child);
                    } else if ($child->hasProp('render')) {
                        $array[] = $this->createRender($child);
                    } else if ($child->hasProp('raw')) {
                        $array[] = $this->createRaw($child);
                    } else if ($child->hasProp('use')) {
                        $this->useInstructions[] = $child->props['use'];
                    } else {
                        $array = array_merge($array, $this->childrenToArray($child->children));
                    }
                } else {
                    $array[] = [
                        'type' => 'node',
                        'tag' => $child->name,
                        'props' => $child->props,
                        'children' => $this->childrenToArray($child->children)
                    ];
                }
            } else if ($child->type === 'text') {
                $text = $child->children[0];
                if (empty($text))
                    continue;
                if (empty(trim($text))) {
                    $end = end($array);
                    if ($end) {
                        if (array_key_exists('expression', $end))
                            continue;
                        if ($end['type'] === 'whitespace')
                            array_pop($array);
                    }
                    $array[] = ['type' => 'text', 'text' => ' '];
                } else {
                    $array[] = ['type' => 'text', 'text' => $text];
                }
            } else if ($child->type === 'doctype') {
                $this->doctype = $child->children[0];
            }
        }
        return $array;
    }
}
