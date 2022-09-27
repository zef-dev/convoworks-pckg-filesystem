<?php

namespace Convo\Pckg\Filesystem;

use Convo\Core\Util\StrUtil;
use DirectoryIterator;
use Convo\Core\Media\IAudioFile;
use Convo\Core\Media\Mp3Id3File;
use Convo\Core\Workflow\AbstractMediaSourceContext;

class FilesystemMediaContext extends AbstractMediaSourceContext
{
    const MIN_MATCH_PERCENT = 80;

    private $_basePath;
    private $_baseUrl;

    private $_minMatchPercentage;
    private $_search;
    private $_searchFolders;
    
    private $_backgroundUrl;
    private $_defaultSongImageUrl;

    private $_loadedSongs;

    private $_addedSearchFolders = [];

    public function __construct( $properties)
    {
        parent::__construct( $properties);
        
        $this->_basePath        =   $properties['base_path'];
        $this->_baseUrl         =   $properties['base_url'];

        $this->_minMatchPercentage          =   $properties['min_match_percentage'] ?? self::MIN_MATCH_PERCENT;
        $this->_search          =   $properties['search'];
        $this->_searchFolders   =   $properties['search_folders'];
        
        $this->_backgroundUrl           =   $properties['background_url'];
        $this->_defaultSongImageUrl     =   $properties['default_song_image_url'];
    }

    
    
    // MEDIA
    public function getCount() : int
    {
        if ( isset( $this->_loadedSongs)) {
            return count( $this->_loadedSongs);
        }
        
        return iterator_count( $this->getSongs());
    }
    
    
    
    // QUERY
    public function getSongs()
    {
        $base_path      =   $this->getService()->evaluateString( $this->_basePath);
        $base_url       =   $this->getService()->evaluateString( $this->_baseUrl);
        $min_match_percentage         =   $this->getService()->evaluateString( $this->_minMatchPercentage);
        $search         =   $this->getService()->evaluateString( $this->_search);
        $search_folders =   $this->getService()->evaluateString( $this->_searchFolders);
        $artwork        =   $this->getService()->evaluateString( $this->_defaultSongImageUrl);
        $background     =   $this->getService()->evaluateString( $this->_backgroundUrl);

        if (!is_numeric($min_match_percentage)) {
            $min_match_percentage = self::MIN_MATCH_PERCENT;
        }

        if (is_string($min_match_percentage) && is_numeric($min_match_percentage)) {
            $min_match_percentage = intval($min_match_percentage);
        }

        if ($min_match_percentage < 0 || $min_match_percentage > 100) {
            $min_match_percentage = self::MIN_MATCH_PERCENT;
        }

        $model          =   $this->_getQueryModel();
        
        $args           =   [ 'search' => $search, 'search_folders' => $search_folders];
        $args_changed   =   $args != $model['arguments'];
        
        if ( isset( $this->_loadedSongs) && !$args_changed) {
            return new \ArrayIterator( $this->_loadedSongs);
        }
        
        if ( $args_changed) {
            $this->_logger->info( 'Arguments changed. Storing them and rewinding results ...');
            $model['arguments']     =   $args;
            $model['post_index']    =   0;
        }
        
        $this->_loadedSongs     =   [];

        $this->_logger->info( 'Scanning dir ['.$base_path.'] against ['.$search.']['.$search_folders.'] with min match percentage ['.$min_match_percentage.']');

        $folders_only   =   false;
        if ( $search_folders && !$search) {
            $folders_only   =   true;
        }
        
        $root   =   new DirectoryIterator( $base_path);
        
        // READ ROOT FILES
        if ( !$folders_only) {
            $this->_loadedSongs =   array_merge(
                $this->_loadedSongs,
                $this->_readFolderSongs( true, $root, $base_url, $artwork, $background, $search, $min_match_percentage));
        }

        $bestMatchedFolder = $this->_getBestMatchedFolder($search_folders, $base_path, $min_match_percentage);

        foreach( $root as $root_item)
        {
            if ( $root_item->isDot() || $root_item->isFile()) {
                continue;
            }
            
            if ( $root_item->isDir()) 
            {
                if ( !empty($bestMatchedFolder)) {
                    $accepts_folder =   $this->_acceptsFolder( $root_item->getFilename(), $bestMatchedFolder);
                    if ( $folders_only && !$accepts_folder) {
                        continue;
                    }
                    if ( $accepts_folder) {
                        $this->_logger->info( 'Loading full folder dir ['.$root_item->getFilename().']');
                        $this->_loadedSongs =   array_merge(
                            $this->_loadedSongs,
                            $this->_readFolderSongs( false, $root_item, $base_url, $artwork, $background));
                        continue;
                    }
                }
                
                $this->_logger->debug( 'Scanning folder dir ['.$root_item->getFilename().']');
                $this->_loadedSongs =   array_merge(
                    $this->_loadedSongs,
                    $this->_readFolderSongs( false, $root_item, $base_url, $artwork, $background, $search, $min_match_percentage)
                );
            }
        }
        
        $count              =   count( $this->_loadedSongs);
        $count_changed      =   count( $model['playlist']) !== $count; 
        
        $this->_logger->info( 'Found total ['.$count.'] songs');
        
        if ( $count <= 0) {
            $model['playlist']  =   [];
        } else if ( $args_changed || $count_changed) {
            if ( $count_changed) {
                $this->_logger->warning( 'Generating playlist because model and query count are different');
            } else {
                $this->_logger->info( 'Generating playlist because arguments were changed');
            }
            
            $model['playlist'] = range( 0, $count- 1);
            if ( $model['shuffle_status']) {
                $this->_logger->info( 'Shuffling playlist');
                shuffle( $model['playlist']);
            }
        }
        
        $this->_saveQueryModel( $model);
        
        return new \ArrayIterator( $this->_loadedSongs);
    }
    
    private function _readFolderSongs( $root, DirectoryIterator $folder, $baseUrl, $artwork, $background, $search=null) 
    {
        $songs  =   [];
        
        foreach( new DirectoryIterator( $folder->getRealPath()) as $folder_file)
        {
            if ( $folder_file->isDot() || !$folder_file->isFile()) {
                continue;
            }
            
            if ( strtolower( $folder_file->getExtension()) !== 'mp3') {
                continue; 
            }
            
            if ( $root) {
                $file_url   =   $baseUrl.'/'.rawurlencode( $folder_file->getFilename());
            } else {
                $file_url   =   $baseUrl.'/'.rawurlencode( $folder->getFilename()).'/'.rawurlencode( $folder_file->getFilename());
            }
            
            $song       =   new Mp3Id3File( $folder_file->getRealPath(), $file_url, $artwork, $background);
            
            if ( $search) {
                if ( $this->_acceptsSong( $song, $search, $minMatchPercentage)) {
                    $songs[]  =   $song;
                }
            } else {
                $songs[]  =   $song;
            }
        }
        
        return $songs;
    }
    
    /**
     * @param IAudioFile $song
     * @param string $search
     * @return bool
     */
    private function _acceptsSong( $song, $search, $minMatchPercentage) {

        if ( empty( $search)) {
            return true;
        }

        if ( StrUtil::getTextSimilarityPercentageBetweenTwoStrings( $song->getSongTitle(), $search) >= $minMatchPercentage) {
            return true;
        }

        if ( StrUtil::getTextSimilarityPercentageBetweenTwoStrings( $song->getArtist(), $search) >= $minMatchPercentage) {
            return true;
        }
        
        return false;
    }
    
    private function _acceptsFolder( $folder, $searchFolder) {
        
        if ( empty( $searchFolder)) {
            return true;
        }

        if ( stripos( $folder, $searchFolder) !== false) {
            return true;
        }
        
        return false;
    }

    private function _addSearchFolder($folderName, $search) {
        $this->_addedSearchFolders[$folderName] = StrUtil::getTextSimilarityPercentageBetweenTwoStrings($folderName, $search);
    }

    /**
     * @param string $search_folders
     * @param string $base_path
     * @return string
     */
    private function _getBestMatchedFolder(string $search_folders, string $base_path, $minMatchPercentage): string
    {
        $bestMatchedFolder = '';

        if (!empty($search_folders)) {
            $folderNames = scandir($base_path);
            if (!empty($folderNames)) {
                foreach ($folderNames as $folderName) {
                    $this->_addSearchFolder($folderName, $search_folders);
                }
                $value = max($this->_addedSearchFolders);
                if ($value >= $minMatchPercentage) {
                    $key = array_search($value, $this->_addedSearchFolders);
                    if ($key !== false && is_string($key)) {
                        $bestMatchedFolder = $key;
                    }
                }
            }
        }

        return $bestMatchedFolder;
    }

    // UTIL
    public function __toString()
    {
        return parent::__toString().'['.$this->_basePath.']['.$this->_baseUrl.']';
    }
}
