<?php

namespace Convo\Pckg\Filesystem;

use Convo\Core\Media\Mp3Id3File;
use Convo\Core\Util\StrUtil;

class Mp3FileDirectory
{
    private $_logger;
    private $_basePath;
    private $_baseUrl;
    private $_minMatchPercentage = 80;
    private $_items = [];

    public function __construct($logger)
    {
        $this->_logger = $logger;
    }

    public function setLogger($logger) {
        $this->_logger = $logger;
    }

    public function setBasePath($basePath) {
        $this->_basePath = $basePath;
    }

    public function setBaseUrl($baseUrl) {
        $this->_baseUrl = $baseUrl;
    }

    public function setMinMatchPercentage($minMatchPercentage) {
        $this->_minMatchPercentage = $minMatchPercentage;
    }

    public function getItems() {
        return $this->_items;
    }

    public function filter($args = []) {
        $root   =   new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator($this->_basePath));
        $this->_items = [];
        if (!isset($args['search']) && !isset($args['search_folders'])) {
            foreach( $root as $root_item) {
                /**
                 * @var $root_item \SplFileInfo
                 */
                if ($root_item->isDir()) {
                    continue;
                }

                $item['file_name'] = str_replace('.'.$root_item->getExtension(), '', $root_item->getFilename());
                $item['real_path'] = $root_item->getRealPath();
                $item['time_created'] = filemtime($root_item->getRealPath());
                $url = $this->_generateFileUrl($root_item->getRealPath());
                $song = new Mp3Id3File($root_item->getRealPath(), $url);
                $item['id3_tag_artist'] = $song->getArtist();
                $item['id3_tag_song_title'] = $song->getSongTitle();
                $item['file_url'] = $song->getFileUrl();
                $item['match_score'] = 0;
                $this->_items[] = $item;
            }

            return $this->_items;
        }

        if (preg_match('/"([^"]+)"/', $args['search'], $m)) {
            $this->_logger->warning( 'Quickfix: Correcting search term ['.$args['search'].'] to ['.$m[1].']');
            $args['search'] = $m[1];
        }
        $this->_logger->info('Going to read files in path ['.$this->_basePath.']');

        $items = [];
        foreach( $root as $root_item) {
            /**
             * @var $root_item \SplFileInfo
             */
            if ($root_item->isDir()) {
                continue;
            }

            $item['file_name'] = str_replace('.'.$root_item->getExtension(), '', $root_item->getFilename());
            $item['real_path'] = $root_item->getRealPath();
            $item['time_created'] = filemtime($root_item->getRealPath());
            $url = $this->_generateFileUrl($root_item->getRealPath());
            $song = new Mp3Id3File($root_item->getRealPath(), $url);
            $item['id3_tag_artist'] = $song->getArtist();
            $item['id3_tag_song_title'] = $song->getSongTitle();
            $item['file_url'] = $song->getFileUrl();
            $item['match_score'] = $this->_calculateMatchScore($root_item->getRealPath(), $song, $args);
            $items[] = $item;
        }
        $maxMatchScore = max(array_column($items, 'match_score'));
        if (empty($maxMatchScore)) {
            return $this->_items;
        }
        $this->_logger->info('Got max match score ['.$maxMatchScore.']');
        foreach ($items as $mp3File) {
            if ($mp3File['match_score'] === $maxMatchScore) {
                $this->_items[] = $mp3File;
            }
        }

        return $this->_items;
    }

    public function sort($args) {
        if (!isset($args['orderby'])) {
            throw new \Exception('The [orderby] parameter must be present.');
        }
        if (!isset($args['order'])) {
            $args['order'] = 'DESC';
            $this->_logger->debug('Setting order to ['.$args['order'].'] as default order.');
        }

        switch ($args['orderby']) {
            case 'time_created':
                if ($args['order'] === 'DESC') {
                    usort($this->_items, function($first, $second){
                        return $first['time_created'] < $second['time_created'];
                    });
                } else if ($args['order'] === 'ASC') {
                    usort($this->_items, function($first, $second){
                        return $first['time_created'] > $second['time_created'];
                    });
                }
                break;
            case 'name':
                if ($args['order'] === 'DESC') {
                    usort($this->_items, function($first, $second){
                        return strtolower($first['file_name']) < strtolower($second['file_name']);
                    });
                } else if ($args['order'] === 'ASC') {
                    usort($this->_items, function($first, $second){
                        return strtolower($first['file_name']) > strtolower($second['file_name']);
                    });
                }
                break;
            default:
                throw new \Exception('The provided sort option ['.$args['orderby'].'] is not supported.');
        }
        return $this->_items;
    }

    private function _calculateMatchScore($realPath, $song, $args) {
        /**
         * @var Mp3Id3File $song
         */
        // TODO implement search folder only
        $searchTerm = $args['search'];
        $folders_only   =   false;
        if ( empty($args['search']) && isset($args['search_folders'])) {
            $folders_only   =   true;
            $searchTerm = $args['search_folders'];
        }

        $searchTerm = strtolower($searchTerm);
        $searchTerm = preg_replace('/[^a-zA-Z0-9]+/', ' ', $searchTerm);
        $realPathParts = explode(DIRECTORY_SEPARATOR, $realPath);
        $matchScore = 0;
        $matchCount = 0;

        if ($folders_only) {
            array_pop($realPathParts);
        }

        $searchTermParts = explode(' ', $searchTerm);
        foreach ($realPathParts as $realPathPart) {
            $realPathPart = preg_replace('/[^a-zA-Z0-9]+/', ' ', $realPathPart);

            foreach (explode(' ', $realPathPart) as $realPathPartWord) {
                foreach ($searchTermParts as $searchTermPart) {
                    if (StrUtil::getTextSimilarityPercentageBetweenTwoStrings(strtolower($searchTermPart), strtolower($realPathPartWord)) >= $this->_minMatchPercentage) {
                        $matchCount++;
                    }
                }
            }
            if (!empty($matchCount)) {
                $matchScore = $matchCount;
            }
        }

        if (!$folders_only) {
            $artist = $song->getArtist();
            if (!empty($artist)) {
                $artistParts = explode(' ', $artist);
                foreach ($artistParts as $artistPart) {
                    $artistPart = preg_replace('/[^a-zA-Z0-9]+/', ' ', $artistPart);

                    foreach ($searchTermParts as $searchTermPart) {
                        if (StrUtil::getTextSimilarityPercentageBetweenTwoStrings(strtolower($searchTermPart), strtolower($artistPart)) >= $this->_minMatchPercentage) {
                            $matchScore++;
                        }
                    }
                }
            }

            $songTitle = $song->getSongTitle();
            $songTitleParts = explode(' ', $songTitle);
            foreach ($songTitleParts as $songTitlePart) {
                $songTitlePart = preg_replace('/[^a-zA-Z0-9]+/', ' ', $songTitlePart);

                foreach ($searchTermParts as $searchTermPart) {
                    if (StrUtil::getTextSimilarityPercentageBetweenTwoStrings(strtolower($searchTermPart), strtolower($songTitlePart)) >= $this->_minMatchPercentage) {
                        $matchScore++;
                    }
                }
            }
        }

        return $matchScore;
    }

    private function _generateFileUrl($realPath) {
        $targetPath = parse_url($this->_baseUrl, PHP_URL_PATH);
        $urlPath = str_replace(DIRECTORY_SEPARATOR, '/', $realPath);
        $urlPathParts = explode('/', $urlPath);
        $urlPath = '';
        foreach ($urlPathParts as $urlPathPart) {
            $urlPath .= '/'.rawurldecode($urlPathPart);
        }
        $urlPath = strstr($urlPath, $targetPath);
        return str_replace($targetPath, '', $this->_baseUrl) . '' .$urlPath;
    }
}
