# ZianoType

[![License](https://img.shields.io/badge/License-zlib/libpng-blue.svg)](https://github.com/Lusito/ziano-type/blob/master/LICENSE)

ZianoType is a template engine for PHP that is inspired by the react JSX syntax.

### Why ZianoType?

- The language
  - is valid HTML. Any HTML editor will do fine.
  - is inspired by react JSX syntax and is more intuitive to read than twig or other template languages.
  - transpiles to native PHP code that gets cached on the disk.
- It supports multiple lookup folders (i.e. if a file wasn't found in `themes/cool`, it checks `themes/default` as well).
- It's easy to include other templates and pass parameters as well as innerHTML to it.
- Write conditions and loops using PHP variables and functions.
- Echo variables using PHP variables without having to write `<?php` or `<?=` tags.
- If an included template fails to render, it will render an error message and continue with the rest to prevent the entire page from breaking.
- ZianoType is released under the liberal zlib/png license.

**Fair warning:** This library is currently in its early stage, so it might have bugs. Feel free to write an issue or a pull request if you encounter one.

### Example

This should give you an idea, what a ZianoType template looks like:

```html
<h1 title="$title">$title</h1>
<z if="empty($articles)">
    No articles available
</z>
<z else-if="count($articles) === 1">
    <z include="components/article.html" only="true" />
</z>
<z else>
    <z for-each="$articles as $article">
        <z include="components/article.html" article="$article" only="false" />
    </z>
</z>
```

The `<z>` tag is a special (reserved) tag and is used to control various things depending on the supplied properties.

### Setup

Install via composer:

`composer require lusito/ziano-type:dev-master`


Include the autoloader in your php script, unless you've done that already:

```php
require __DIR__ . '/vendor/autoload.php';
```

In order to catch errors nicely, you should add an error handler, that throws an ErrorException like this:

```php
set_error_handler(function ($errno, $errstr, $errfile, $errline ,array $errcontex) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
```

The only class you need to use is Lusito\ZianoType\Renderer:

```php
use Lusito\ZianoType\Renderer;

$renderer = new Renderer([
    'themes' => [
        // This array will be checked in-order to find template files
        ['extended', 'themes/extended/templates'],
        ['default', 'themes/default/templates']
    ],
    // This is the path of the cache directory
    'cachePath' => __DIR__ . '/cache',
    // Script and stylesheet filepaths can be added here:
    'scripts' => [...],
    'stylesheets' => [...]
]);
// define some properties:
$props = [
    'basepath' => '/ZianoType/example/',
    'siteTitle' => 'ZianoType',
    'pageTitle' => 'example',
    //...
];
$innerHTML = '';
$renderer->render("layout/index.html", $props, $innerHTML);

```
Call render with a fourth parameter set to `true` if you want to get the rendered value as return value instead of printing it to stdout.

See the example folder for a more detailed setup and templates.

### Using Code

#### Printing variables

As you can see in the sample above, you can use native PHP variables right in the HTML and attributes. Just as if you wrote `echo "hello $variable!";`

You can alternatively use curly braces for `{$complex['syntax']}`.

And if you need to write even more complex code, use double curly braces:
`{{trim(' hello' . $title)}}`.

Anything you write like this will be printed in escaped form, i.e. `<` will become  `&lt;`.

If you need to write text in fraw form, use the `<z raw="$value" />`.
Keep in mind though, that template files need to be valid HTML. I.e. you might have to write code like this:
```html
<z raw="&lt;i title=&quot;woot&quot;&gt;text&lt;/i&gt;" />
<!-- Result: -->
<i title="woot">text</i>
```

But if you find yourself doing stuff like that in the template, it's probably a good idea to rethink your approach and pass that raw text as a variable instead.

#### The `<z>` tag

Depending on what properties you add to the `<z>` tag, it does different things. Everything between the tag start and tag end will be used as innerHTML for the respective purpose:

- `if="$condition"`, `else-if="$condition"`, `else`.
  - These control structures allow you to create conditions. The innerHTML will be included if the condition is met. Normal PHP code is expected as condition.
- `for-each="$items as $item"` or `for-each="$items as $key=>$value"`.
  - Just as you would expect, it loops over all items and includes innerHTML for each item.
- `include="path/to/file.html"`.
  - This allows you to include another template. All additional properties of the `<z>` tag will be passed to the included template as PHP variable. Additionally, the innerHTML will be passed to the template as well, but must be included manually using `<z render="innerHTML">`.
- `render="innerHTML"` renders the innerHTML, which was passed to this template from the outside.
- `render="scripts"` renders all script tags configured for this render operation.
- `render="stylesheets"` renders all link (stylesheet) tags for this render operation.
- `raw="$value"` renders the content of the raw property without escaping it (see the section above).
- `use="Vendor\Namespace\Class"`.
  - This will write a PHP use-statement at the top of the cached generated PHP code, so you can easily use other classes.

#### The `z-extract="$value"` property

Say, you want to set multiple properties on a tag from code, without wanting to write complex code. 

Somewhere in PHP:
```php
$code = [
    'title' => 'My Title',
    'alt' => 'Alternative Text',
    'src' => 'foo/bar.png'
];
```

Somewhere in the template:
```html
<img z-extract="$code" />
```

This will make sure, that every key-value pair in the contained code will be printed as `$key="$value"` in the tag. It's not yet supported for `<z include="">`, but it's on the todo-list.


### More detailed examples

#### Passing innerHTML and props to another template

Say you have a template named 'elements/article.html':
```html
<h1>$title</h1>
<p><z render="innerHTML" /></p>
```

Then you could include it like this:
```html
<z include="elements/article.html" title="Foo just got barred>
    Foo got a <a href="#link">speeding ticket</a> and is now behind bars.
</z>
```

In the sample above, you see another use of the `<z>` tag: Rendering innerHTML.

### Report isssues

Something not working quite as expected? Do you need a feature that has not been implemented yet? Check the [issue tracker](https://github.com/Lusito/ziano-type/issues) and add a new one if your problem is not already listed. Please try to provide a detailed description of your problem, including the steps to reproduce it.

### Contribute

Awesome! If you would like to contribute with a new feature or submit a bugfix, fork this repo and send a pull request. Please, make sure all the unit tests are passing before submitting and add new ones in case you introduced new features.

### License

ziano-type has been released under the [zlib/libpng](https://github.com/Lusito/ziano-type/blob/master/LICENSE) license, meaning you
can use it free of charge, without strings attached in commercial and non-commercial projects. Credits are appreciated but not mandatory.
