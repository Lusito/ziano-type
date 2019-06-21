<?php
// Report all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_error_handler(function ($errno, $errstr, $errfile, $errline ,array $errcontex) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require __DIR__ . '/../vendor/autoload.php';

use Lusito\ZianoType\Renderer;

$renderer = new Renderer([
    'themes' => [
        ['extended', 'themes/extended/templates'],
        ['default', 'themes/default/templates']
    ],
    'cachePath' => __DIR__ . '/cache',
    'scripts' => [],
    'stylesheets' => [
        'themes/default/styles.css',
        "https://fonts.googleapis.com/css?family=PT+Serif",
        "https://fonts.googleapis.com/css?family=Doppio+One"
    ]
]);
$props = [
    'basepath' => '/ZianoType/example/',
    'siteTitle' => 'ZianoType',
    'pageTitle' => 'example',
    'copyrightNotice' => '© 2019 Santo Pfingsten',
    'menuItems' => include(__DIR__ . '/menu.php.inc')
];

function renderArticle($renderer, $innerHTML, $title, $titleVisible) {
    $props = [
        'title' => $title,
        'titleVisible' => $titleVisible,
        'sectionProps' => [
            'title' => $title,
            'data-label' => $title
        ]
    ];
    return $renderer->render("components/article.html", $props, $innerHTML, true);
}
$articleHTML = '<p>Hello <a href="https://www.github.com/">Github</a>. Show me your repositories!</p>';
$articles = [
    renderArticle($renderer, $articleHTML, "", false),
    renderArticle($renderer, $articleHTML, "Title 2", false),
    renderArticle($renderer, $articleHTML, "Title 3", true),
    renderArticle($renderer, $articleHTML, "", true),
];
$renderer->render("layout/index.html", $props, implode("\n", $articles));
