Graylog log target for Yii2
============================

Credits
-------
Based on [Benjamin Zikarsky gelf-php library](https://github.com/bzikarsky/gelf-php)

Inspired by [Roman Ovchinnikov yii2-graylog2](https://github.com/RomeroMsk/yii2-graylog2) 

Installation
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require "nikitich/yii2-graylog-target" "dev-master"
```

or add

```json
"nikitich/yii2-graylog-target" : "dev-master"
```

to the `require` section of your application's `composer.json` file.

Usage
-----

Add Graylog target to your log component config:
```php
<?php
return [
    ...
    'components' => [
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                'file' => [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
                'graylog' => [
                    'class' => 'nikitich\graylog\GraylogTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => ['application'],
                    'logVars' => [],                            // This prevent yii2-debug from crashing ;)
                    'server_url' => '127.0.0.1',
                    'use_udp' => false,                         // For use HTTP transport
                    'facility' => 'facility-name',
                    'add_fields' => [                           // Additional fields which must be added   
                        'user-ip' => function($yii) {           //  to every message (optional)
                            return $yii->request->getUserIP();  //  can contain callable function which
                        },                                      //  will recieve instance of Yii application
                        'tag' => 'tag-name'                     //  as argument
                    ]
                ],
            ],
        ],
    ],
    ...
];
```

GraylogTarget will use traces array (first element) from log message to set `file` and `line` gelf fields. So if you want to see these fields in Graylog2, you need to set `traceLevel` attribute of `log` component to 1 or more. Also all lines from traces will be sent as `trace` additional gelf field.

You can log not only strings, but also any other types (non-strings will be dumped by `yii\helpers\VarDumper::dumpAsString()`).

By default GraylogTarget will put the entire log message as `short_message` gelf field. But you can set `short_message`, `full_message` and `additionals` by using `'short'`, `'full'` and `'add'` keys respectively:
```php
<?php
// short_message will contain string representation of ['test1' => 123, 'test2' => 456],
// no full_message will be sent
Yii::info([
    'test1' => 123,
    'test2' => 456,
]);

// short_message will contain 'Test short message',
// two additional fields will be sent,
// full_message will contain all other stuff without 'short' and 'add':
// string representation of ['test1' => 123, 'test2' => 456]
Yii::info([
    'test1' => 123,
    'test2' => 456,
    'short' => 'Test short message',
    'add' => [
        'additional1' => 'abc',
        'additional2' => 'def',
    ],
]);

// short_message will contain 'Test short message',
// two additional fields will be sent,
// full_message will contain 'Test full message', all other stuff will be lost
Yii::info([
    'test1' => 123,
    'test2' => 456,
    'short' => 'Test short message',
    'full' => 'Test full message',
    'add' => [
        'additional1' => 'abc',
        'additional2' => 'def',
    ],
]);
```
