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
        
        $model              =   $this->_getQueryModel();
        
        $args               =   ['search' => $search];
        $args_changed       =   $args != $model['arguments'];
        
        if ( isset( $this->_loadedSongs) && !$args_changed) {
            return new \ArrayIterator( $this->_loadedSongs);
        }
        
        if ( $args_changed) {
            $this->_logger->info( 'Arguments changed. Storing them and rewinding results ...');
            $model['arguments']     =   $args;
            $model['post_index']    =   0;
        }
        
        $this->_loadedSongs     =   [];
        
        $this->_logger->info( 'Scanning dir ['.$base_path.'] against ['.$search.']');
        
        foreach( new DirectoryIterator( $base_path) as $root_item) 
        {
            if ( $root_item->isDot()) {
                continue;
            }
            
            if ( $root_item->isFile()) 
            {
                $file_path  =   $root_item->getRealPath();
                $file_url   =   $base_url.'/'.rawurlencode( $root_item->getFilename());
                $song       =   new Mp3Id3File( $file_path, $file_url);
                
                if ( $this->_acceptsSong( $song, $search)) {
                    $this->_loadedSongs[]  =   $song;
                }
                continue;
            } 
            
            if ( $root_item->isDir()) 
            {
                foreach( new DirectoryIterator( $root_item->getRealPath()) as $folder_file)
                {
                    if ( $folder_file->isDot() || !$folder_file->isFile()) {
                        continue;
                    }
                    
                    $file_path  =   $folder_file->getRealPath();
                    $file_url   =   $base_url.'/'.rawurlencode( $root_item->getFilename()).'/'.rawurlencode( $folder_file->getFilename());
                    $song       =   new Mp3Id3File( $file_path, $file_url);
                    
                    if ( $this->_acceptsSong( $song, $search)) {
                        $this->_loadedSongs[]  =   $song;
                    }
                }
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
    
    
    // UTIL
    public function __toString()
    {
        return parent::__toString().'['.$this->_basePath.']['.$this->_baseUrl.']';
    }
}
