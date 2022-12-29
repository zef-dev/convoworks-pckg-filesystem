<?php

namespace Convo\Pckg\Filesystem;


use Convo\Core\Util\StrUtil;

class Mp3Filter
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $_logger;

    /**
     * @var Mp3InfoProvider
     */
    private $_provider;

    private $_searchFile;
    private $_searchFolder;
    private $_minPercentage;
    
    public function __construct( $logger, $provider, $minPercentage, $searchFile, $searchFolder)
    {
        $this->_logger          =   $logger;
        $this->_provider        =   $provider;
        $this->_minPercentage   =   $minPercentage;
        $this->_searchFile      =   strtolower( $searchFile);
        $this->_searchFolder    =   strtolower( $searchFolder);
    }
    
    public function hasSearch()
    {
        return !( empty( $this->_searchFile) && empty( $this->_searchFolder));
    }
    
    /**
     * @param \SplFileInfo $folder
     * @return boolean
     */
    public function matchFolder( $folder)
    {
        $folder_name    =   strtolower( $folder->getBasename( '.' . $folder->getExtension()));
        $folder_name    =   preg_replace( '/[^a-zA-Z0-9]+/', ' ', $folder_name);
        
        if ( $folder_name === $this->_searchFolder) {
            $this->_logger->debug( 'Exact folder match ['.$folder_name.']');  
            return true;
        }
        
        if ( strpos( $folder_name, $this->_searchFolder) !== false) {
            $this->_logger->debug( 'Strpos folder match ['.$folder_name.']['.$folder->getFilename().']');
            return true;
        }
        
        if ( StrUtil::getTextSimilarityPercentageBetweenTwoStrings( $this->_searchFolder, $folder_name) >= $this->_minPercentage) {
            $this->_logger->debug( 'Strpos folder fuzzy match ['.$folder_name.']');
            return true;
        }
        
        return false;
    }
    
    /**
     * @param \SplFileInfo $file
     * @return boolean
     */
    public function matchFile( $file)
    {
        $file_name  =   strtolower( $file->getBasename( '.' . $file->getExtension()));
        $file_name  =   preg_replace( '/[^a-zA-Z0-9]+/', ' ', $file_name);
        
        if ( strpos( $file_name, $this->_searchFile) !== false) {
            $this->_logger->debug( 'Strpos file match ['.$file_name.']['.$file->getFilename().']');
            return true;
        }

        $song = $this->_provider->getMp3Info( $file);
        
        if ( strpos( $song->getSongTitle(), $this->_searchFile) !== false) {
            $this->_logger->debug( 'Strpos song title ['.$song->getSongTitle().']');
            return true;
        }
        
        if ( strpos( $song->getArtist(), $this->_searchFile) !== false) {
            $this->_logger->debug( 'Strpos song artist against search file ['.$song->getArtist().']');
            return true;
        }
        
        if ( strpos( $song->getArtist(), $this->_searchFolder) !== false) {
            $this->_logger->debug( 'Strpos song artist against search folder ['.$song->getArtist().']');
            return true;
        }
        
        if ( StrUtil::getTextSimilarityPercentageBetweenTwoStrings( $this->_searchFile, $file_name) >= $this->_minPercentage) {
            $this->_logger->debug( 'Strpos filename fuzzy match ['.$file_name.']');
            return true;
        }
        
        return false;
    }
    
    
    // UTIL
    public function __toString()
    {
        return get_class( $this).'['.$this->_searchFile.']['.$this->_searchFolder.']';
    }

}
