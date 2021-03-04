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
            return $this->_loadedSongs;
        }
        
        return iterator_count( $this->getSongs());
    }
    
    
    
    // QUERY
    public function getSongs()
    {
        $base_path      =   $this->_evaluateSting( $this->_basePath);
        $base_url       =   $this->_evaluateSting( $this->_baseUrl);
        $search         =   $this->_evaluateSting( $this->_search);
        
        if ( isset( $this->_loadedSongs)) {
            return new \ArrayIterator( $this->_loadedSongs);
        }
        
        $this->_loadedSongs          =   [];
        
        foreach( new DirectoryIterator( $base_path) as $root_item) 
        {
            if ( $root_item->isDot()) {
                continue;
            }
            
            if ( $root_item->isFile()) 
            {
                $file_path  =   $root_item->getRealPath();
                $file_url   =   $base_url.'/'.$root_item->getFilename();
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
                    $file_url   =   $base_url.'/'.$folder_file->getFilename();
                    $song       =   new Mp3Id3File( $file_path, $file_url);
                    
                    if ( $this->_acceptsSong( $song, $search)) {
                        $this->_loadedSongs[]  =   $song;
                    }
                }
            }
        }
        
        return new \ArrayIterator( $this->_loadedSongs);
    }
    
    /**
     * @param IAudioFile $song
     * @param string $search
     * @return bool
     */
    private function _acceptsSong( $song, $search) {
        
        $this->_logger->warning( 'Not implemenetd');
        
        return true;
    }
    
    
    // UTIL
    public function __toString()
    {
        return parent::__toString().'['.$this->_basePath.']['.$this->_baseUrl.']';
    }
}
