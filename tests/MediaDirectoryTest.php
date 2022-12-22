<?php

namespace Convo;

use Convo\Core\Util\Test\ConvoTestCase;

class FileDirectoryTest extends ConvoTestCase
{

    const BASE_URL = 'https://example.com/mp3/';
    private $_mediaDirectory;

    public function setUp(): void
    {
        parent::setUp();
        $this->_mediaDirectory = new \Convo\Pckg\Filesystem\Mp3FileDirectory($this->_logger);
    }

    /**
     * @dataProvider filterProvider
     * @return void
     * @throws \Exception
     */
    public function testFilterItems($basePath, $minMatchPercentage, $filterArgs, $expected)
    {
        $this->_mediaDirectory->setBasePath($basePath);
        $this->_mediaDirectory->setBaseUrl(self::BASE_URL);
        $this->_mediaDirectory->setMinMatchPercentage($minMatchPercentage);
        $items = $this->_mediaDirectory->filter($filterArgs);
        $search = $filterArgs['search'] ?? '';
        $search_folders = $filterArgs['search_folders'] ?? '';
        $this->_logger->info('Printing found items ['.json_encode($items, JSON_PRETTY_PRINT).'] for search term ['.$search.'] and search folder ['.$search_folders.']');
        $this->assertEquals($expected, count($items));
    }

    public function testFileUrls() {
        $this->_mediaDirectory->setBasePath(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3');
        $this->_mediaDirectory->setBaseUrl(self::BASE_URL);
        $this->_mediaDirectory->setMinMatchPercentage(90);
        $filterArgs['search'] = 'deathloop';
        $items = $this->_mediaDirectory->filter($filterArgs);
        $this->_logger->info('Printing found items ['.json_encode($items, JSON_PRETTY_PRINT).'] for search term ['.$filterArgs['search'].']');
        $this->assertEquals(12, count($items));
    }

    /**
     * @dataProvider sortProvider
     * @return void
     * @throws \Exception
     */
    public function testSortItems($basePath, $minMatchPercentage, $filterArgs, $sortArgs, $expected) {
        $this->_mediaDirectory->setBasePath($basePath);
        $this->_mediaDirectory->setBaseUrl(self::BASE_URL);
        $this->_mediaDirectory->setMinMatchPercentage($minMatchPercentage);
        $this->_mediaDirectory->filter($filterArgs);
        $items = $this->_mediaDirectory->sort($sortArgs);
        $this->_logger->info('Printing items ['.json_encode($items, JSON_PRETTY_PRINT).']');
        $this->assertEquals($expected, $items[0]['file_name']);
    }

    public function filterProvider() {
        return [
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'rap',
                    'search_folders' => ''
                ],
                4
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'deathloop',
                    'search_folders' => ''
                ],
                12
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'steve cash',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'johnny cash',
                    'search_folders' => ''
                ],
                2
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'MunzadetH',
                    'search_folders' => ''
                ],
                2
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'final area',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'area select',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Elvis Presley',
                    'search_folders' => ''
                ],
                3
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Oliver Onions',
                    'search_folders' => ''
                ],
                2
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Bud Spencer',
                    'search_folders' => ''
                ],
                2
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'buggy',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Bud Spencer Buggy',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Oliver Onions Dine Buggy',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Oliver Onions Flying Trough the Air',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'The Quemists',
                    'search_folders' => ''
                ],
                2
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'The Quemists Everything is Under Control',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Tom Salta',
                    'search_folders' => ''
                ],
                7
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Tom Salta The Complex',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Kate Bush',
                    'search_folders' => ''
                ],
                5
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Tom Waits',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'kite',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'bomba',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '666',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'bomba 666',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '666 bomba',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '666-bomba',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '666 - bomba',
                    'search_folders' => ''
                ],
                1
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '',
                    'search_folders' => 'deathloop'
                ],
                12
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '',
                    'search_folders' => 'movies'
                ],
                2
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '',
                    'search_folders' => 'Bud Spencer'
                ],
                2
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '',
                    'search_folders' => 'oldies'
                ],
                3
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '',
                    'search_folders' => 'Oliver Onions'
                ],
                0
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '',
                    'search_folders' => 'The Quemists'
                ],
                2
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '',
                    'search_folders' => 'rap'
                ],
                4
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '',
                    'search_folders' => ''
                ],
                0
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'Shaggy',
                    'search_folders' => ''
                ],
                0
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => '',
                    'search_folders' => 'Shaggy'
                ],
                0
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [],
                36
            ]
        ];
    }

    public function sortProvider() {
        return [
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'deathloop'
                ],
                [
                    'orderby' => 'name',
                    'order'=>'DESC'
                ],
                '12 - Deja-Vu'
            ],
            [
                __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mp3',
                90,
                [
                    'search' => 'deathloop'
                ],
                [
                    'orderby' => 'name',
                    'order'=>'ASC'
                ],
                '01 - Blackreef'
            ]
        ];
    }
}
