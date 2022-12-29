<?php

namespace Convo\Pckg\Filesystem;


class Mp3DirectoryReader
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;
    
    /**
     * @var Mp3InfoProvider
     */
    private $_provider;
    
    /**
     * @var Mp3Filter
     */
    private $_filter;
    
    
    public function __construct( $logger, $provider, $filter)
    {
        $this->_logger      =   $logger;
        $this->_provider    =   $provider;
        $this->_filter      =   $filter;
    }
    
    /**
     * @param string $path
     * @return \Convo\Core\Media\IAudioFile[]
     */
    public function readFolder( $path)
    {
        if ( !$this->_filter->hasSearch()) {
            $this->_logger->info( 'Returniing all from ['.$path.']');
            return $this->_readFolder( $path);
        }
        $this->_logger->info( 'Scanning files from ['.$path.']');
        return $this->_scanFolder( $path);
    }

    private function _scanFolder( $path)
    {
        $items      =   [];
        $own_items  =   [];
        $root       =   new \DirectoryIterator( $path);
        
        foreach( $root as $root_item)
        {
            if ( $root_item->isDot()) {
                continue;
            }
            
            if ( $root_item->isFile() && !$this->_isValidFile( $root_item)) {
                continue;
            }
            
            if ( $root_item->isDir()) {
                if ( $this->_filter->matchFolder( $root_item)) {
                    $items = array_merge( $items, $this->_readFolder( $root_item->getRealPath()));
                } else {
                    $items = array_merge( $items, $this->_scanFolder( $root_item->getRealPath()));
                }
                continue;
            }
            
            if ( $this->_filter->matchFile( $root_item)) {
                $own_items[] = $this->_provider->getMp3Info( $root_item);
            }
        }
        
        $items = array_merge( $own_items, $items);
        
        return $items;
    }
    
    private function _readFolder( $path)
    {
        $items      =   [];
        $own_items  =   [];
        $root       =   new \DirectoryIterator( $path);
        
        foreach( $root as $root_item)
        {
            if ( $root_item->isDot()) {
                continue;
            }
            
            if ( $root_item->isFile() && !$this->_isValidFile( $root_item)) {
                continue;
            }
            
            if ( $root_item->isDir()) {
                $items = array_merge( $items, $this->_readFolder( $root_item->getRealPath()));
                continue;
            }
            
            $own_items[] = $this->_provider->getMp3Info( $root_item);
        }
        
        $items = array_merge( $own_items, $items);
        
        return $items;
    }
    
    private function _sort( $items) 
    {
        usort( $items, function( $first, $second){
            return strtolower( $first->getFilename()) > strtolower( $second->getFilename());
        });
        
        return $items;
    }
    
    /**
     * @param \SplFileInfo $file
     * @return boolean
     */
    private function _isValidFile( $file)
    {
        if ( strtolower( $file->getExtension()) !== 'mp3') {
            $this->_logger->debug( 'Not valid file ['.$file->getFilename().']');
            return false;
        }
        
        return true;
    }
    
    
    // UTIL
    public function __toString()
    {
        return get_class( $this).'[]';
    }
}
