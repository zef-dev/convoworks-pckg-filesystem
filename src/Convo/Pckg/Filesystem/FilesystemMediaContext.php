<?php

namespace Convo\Pckg\Filesystem;

use DirectoryIterator;
use Convo\Core\Media\IAudioFile;
use Convo\Core\Media\Mp3Id3File;
use Convo\Core\Workflow\AbstractMediaSourceContext;

class FilesystemMediaContext extends AbstractMediaSourceContext
{

    private $_basePath;
    private $_baseUrl;
    
    private $_search;
    private $_searchFolders;
    
    private $_backgroundUrl;
    private $_defaultSongImageUrl;

    private $_loadedSongs;
    
    public function __construct( $properties)
    {
        parent::__construct( $properties);
        
        $this->_basePath        =   $properties['base_path'];
        $this->_baseUrl         =   $properties['base_url'];
        
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
        $search         =   $this->getService()->evaluateString( $this->_search);
        $search_folders =   $this->getService()->evaluateString( $this->_searchFolders);
        $artwork        =   $this->getService()->evaluateString( $this->_defaultSongImageUrl);
        $background     =   $this->getService()->evaluateString( $this->_backgroundUrl);
        
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
        
        $this->_logger->info( 'Scanning dir ['.$base_path.'] against ['.$search.']['.$search_folders.']');
        
        $folders_only   =   false;
        if ( $search_folders && !$search) {
            $folders_only   =   true;
        }
        
        $root   =   new DirectoryIterator( $base_path);
        
        // READ ROOT FILES
        if ( !$folders_only) {
            $this->_loadedSongs =   array_merge(
                $this->_loadedSongs,
                $this->_readFolderSongs( true, $root, $base_url, $artwork, $background, $search));
        }
        
        
        foreach( $root as $root_item) 
        {
            if ( $root_item->isDot() || $root_item->isFile()) {
                continue;
            }
            
            if ( $root_item->isDir()) 
            {
                if ( $folders_only) {
                    if ( !$this->_acceptsFolder( $root_item->getFilename(), $search_folders)) {
                        continue;
                    }
                    $this->_logger->info( 'Loading full folder dir ['.$root_item->getFilename().']');
                    $this->_loadedSongs =   array_merge(
                        $this->_loadedSongs,
                        $this->_readFolderSongs( false, $root_item, $base_url, $artwork, $background));
                    continue;
                }
                
                $this->_logger->debug( 'Scanning folder dir ['.$root_item->getFilename().']');
                $this->_loadedSongs =   array_merge(
                    $this->_loadedSongs,
                    $this->_readFolderSongs( false, $root_item, $base_url, $artwork, $background, $search));
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
            
            if ( $root) {
                $file_url   =   $baseUrl.'/'.rawurlencode( $folder_file->getFilename());
            } else {
                $file_url   =   $baseUrl.'/'.rawurlencode( $folder->getFilename()).'/'.rawurlencode( $folder_file->getFilename());
            }
            
            $song       =   new Mp3Id3File( $folder_file->getRealPath(), $file_url, $artwork, $background);
            
            if ( $search) {
                if ( $this->_acceptsSong( $song, $search)) {
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
    private function _acceptsSong( $song, $search) {
        
        if ( empty( $search)) {
            return true;
        }
        
        if ( stripos( $song->getSongTitle(), $search) !== false) {
            return true;
        }
        
        if ( stripos( $song->getArtist(), $search) !== false) {
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
    
    
    // UTIL
    public function __toString()
    {
        return parent::__toString().'['.$this->_basePath.']['.$this->_baseUrl.']';
    }
}
