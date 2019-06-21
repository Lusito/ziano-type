<?php
return [
    [
        'label' => 'Home',
        'href' => '#home',
    ],
    [
        'label' => 'Sub',
        'href' => '#sub',
        'children' => [
            [ 'label' => 'First', 'href' => '#first' ],
            [
                'label' => 'second',
                'children' => [
                    [ 'label' => 'A' ],
                    [ 'label' => 'B', 'href' => '#B' ],
                    [ 'label' => 'C' ],
                ]
            ]
        ]
    ]
];
