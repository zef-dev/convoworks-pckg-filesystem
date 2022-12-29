<?php

namespace Convo\Pckg\Filesystem;


use Convo\Core\Media\Mp3Id3File;
use Convo\Core\Media\IAudioFile;

class Mp3InfoProvider
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;

    /**
     * @var IAudioFile[]
     */
    private $_cache =   [];
    
    private $_baseUrl;
    private $_artwork;
    private $_background;
    
    public function __construct( $logger, $baseUrl, $artwork, $background)
    {
        $this->_logger      =   $logger;
        $this->_baseUrl     =   $baseUrl;
        $this->_artwork     =   $artwork;
        $this->_background  =   $background;
    }
    
    
    /**
     * @param \SplFileInfo $file
     * @return IAudioFile
     */
    public function getMp3Info( $file)
    {
        if ( !isset( $this->_cache[ $file->getRealPath()])) {
            $this->_cache[ $file->getRealPath()] = new Mp3Id3File( 
                $file->getRealPath(), $this->_generateFileUrl( $file->getRealPath()), $this->_artwork, $this->_background);
        }
        return $this->_cache[ $file->getRealPath()];
    }
    
    private function _generateFileUrl( $realPath) 
    {
        $targetPath     =   parse_url( $this->_baseUrl, PHP_URL_PATH);
        $urlPath        =   str_replace(DIRECTORY_SEPARATOR, '/', $realPath);
        $urlPathParts   =   explode('/', $urlPath);
        $urlPath        =   '';
        foreach ( $urlPathParts as $urlPathPart) {
            $urlPath .= '/'.rawurldecode( $urlPathPart);
        }
        $urlPath        =   strstr( $urlPath, $targetPath);
        return str_replace( $targetPath, '', $this->_baseUrl) . '' .$urlPath;
    }
    
    // UTIL
    public function __toString()
    {
        return get_class( $this).'['.count( $this->_cache).']['.$this->_baseUrl.']['.$this->_artwork.']['.$this->_background.']';
    }
}
