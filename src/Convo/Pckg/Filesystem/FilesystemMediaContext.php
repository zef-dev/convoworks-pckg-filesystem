<?php


namespace Convo\Pckg\Filesystem;


use Convo\Core\DataItemNotFoundException;
use Convo\Core\Params\IServiceParamsScope;
use Convo\Core\Media\Mp3File;
use Convo\Core\Workflow\AbstractBasicComponent;
use Convo\Core\Workflow\IMediaSourceContext;
use DirectoryIterator;
use Convo\Core\Media\IAudioFile;

class FilesystemMediaContext extends AbstractBasicComponent implements IMediaSourceContext
{

    private $_id;

    private $_folderPath;
    private $_baseUrl;
    
    private $_search;
    private $_searchFolderl;


    public function __construct( $properties)
    {
        parent::__construct( $properties);
        
        $this->_id              =   $properties['id'];
        
        $this->_folderPath      =   $properties['folder_path'];
        $this->_baseUrl         =   $properties['base_url'];
        
        $this->_search          =   $properties['search'];
        $this->_searchFolderl   =   $properties['search_folders'];
    }

    
    /**
     * {@inheritDoc}
     * @see \Convo\Core\Workflow\IServiceContext::init()
     */
    public function init()
    {
    }
    
    
    /**
     * {@inheritDoc}
     * @see \Convo\Core\Workflow\AbstractBasicComponent::getId()
     */
    public function getId()
    {
        return $this->_id;
    }
    
    /**
     * @return IMediaSourceContext
     */
    public function getComponent()
    {
        return $this;
    }
    
    
    // MEDIA
    public function isEmpty() : bool
    {
        return empty( $this->getCount());
    }
    
    public function isLast() : bool
    {
        $model          =   $this->_getQueryModel();
        return $model['post_index'] >= $this->getCount();
    }
    
    public function getCount() : int
    {
//         $query  =   $this->getWpQuery();
//         return $query->post_count;
    }
    
    public function next() : IAudioFile
    {
        $query      =   $this->getWpQuery();
        if ( $query->found_posts === 1 && $this->getLoopStatus()) {
            return $this->_getSong( 0);
        }
        
        if ( $this->isLast()) {
            if ( !$this->getLoopStatus()) {
                throw new DataItemNotFoundException( 'Can\'t get next. Loop is off and we are on the last result.');
            }
            return $this->_getSong( 0);
        }
        $model      =   $this->_getQueryModel();
        return $this->_getSong( $model['post_index'] + 1);
    }
    
    public function current() : IAudioFile {
        $model      =   $this->_getQueryModel();
        return $this->_getSong( $model['post_index']);
    }
    
    public function movePrevious() {
        $model      =   $this->_getQueryModel();
        $previous   =   $model['post_index'] - 1;
        if ( $previous < 0) {
            if ( !$model['loop_status']) {
                throw new DataItemNotFoundException( 'Can\'t move previous. Already at last first result');
            }
            $query      =   $this->getWpQuery();
            $previous   =   $query->post_count - 1;
        }
        $model['post_index'] = $previous;
        $this->_saveQueryModel( $model);
    }
    
    public function moveNext() {
        $query  =   $this->getWpQuery();
        $model  =   $this->_getQueryModel();
        $next   =   $model['post_index'] + 1;
        if ( $next > $query->post_count - 1) {
            if ( !$model['loop_status']) {
                throw new DataItemNotFoundException( 'Can\'t move next. Already at last result ['.$query->post_count.']');
            }
            $next   =   0;
        }
        $model['post_index'] = $next;
        $this->_saveQueryModel( $model);
    }
    
    public function seek( $index) {
        $query  =   $this->getWpQuery();
        
        if ( $index > $query->post_count - 1) {
            throw new DataItemNotFoundException( 'Can\'t move to the ['.$index.']. There are only ['.$query->post_count.'] songs');
        }
        if ( $index < 0) {
            throw new DataItemNotFoundException( 'Can\'t move to the ['.$index.'].');
        }
        
        $model  =   $this->_getQueryModel();
        $model['post_index'] = $index;
        $this->_saveQueryModel( $model);
    }
    
    public function rewind() {
        $model  =   $this->_getQueryModel();
        $model['post_index'] = 0;
        $this->_saveQueryModel( $model);
    }
    
    public function getOffset() : int {
        $model  =   $this->_getQueryModel();
        return $model['song_offset'];
    }
    public function setOffset( $offset) {
        $model  =   $this->_getQueryModel();
        $model['song_offset'] = $offset;
        $this->_saveQueryModel( $model);
    }
    
    public function setStopped( $offset=-1) {
        $model  =   $this->_getQueryModel();
        if ( $offset >= 0) {
            $model['song_offset'] = $offset;
        }
        $model['playing'] = false;
        $this->_saveQueryModel( $model);
    }
    
    public function setPlaying()
    {
        $model  =   $this->_getQueryModel();
        $model['playing'] = true;
        $this->_saveQueryModel( $model);
    }
    
    public function setLoopStatus( $loopStatus) {
        $model  =   $this->_getQueryModel();
        $model['loop_status'] = $loopStatus;
        $this->_saveQueryModel( $model);
    }
    public function getLoopStatus() : bool {
        $model  =   $this->_getQueryModel();
        return $model['loop_status'];
    }
    
    public function setShuffleStatus( $shuffleStatus) {
        $model  =   $this->_getQueryModel();
        $model['shuffle_status'] = $shuffleStatus;
        if ( $shuffleStatus) {
            $this->_logger->info( 'Reseting post index and shuffling playlist');
            $model['post_index'] = 0;
            shuffle( $model['playlist']);
        } else {
            $real_index             =   $model['playlist'][$model['post_index']];
            $this->_logger->info( 'Using real post index ['.$real_index.']');
            $model['post_index']    =   $real_index;
            sort( $model['playlist']);
        }
        $this->_saveQueryModel( $model);
    }
    public function getShuffleStatus() : bool {
        $model  =   $this->_getQueryModel();
        return $model['shuffle_status'];
    }
    
    
    // INFO
    public function getMediaInfo() : array
    {
        $info   =   IMediaSourceContext::DEFAULT_MEDIA_INFO;
        
        // has to be before _getQueryModel() is called
        if ( !$this->isEmpty()) {
            $info['current'] = $this->current();
            try {
                $info['next'] = $this->next();
            } catch ( DataItemNotFoundException $e) {
                $this->_logger->debug( $e->getMessage());
            }
        }
        
        $model  =   $this->_getQueryModel();
        
        $info   =   array_merge( $info, [
            'count' => $this->getCount(),
            'last' => $this->isLast(),
            'first' => $model['post_index'] === 0,
            'song_no' => $model['post_index'] + 1,
            'loop_status' => $model['loop_status'],
            'shuffle_status' => $model['shuffle_status'],
            'playing' => $model['playing'],
        ]);
        
        $this->_logger->debug( 'Got current media info ['.print_r( $info, true).']');
        
        return $info;
    }
    
    // QUERY
    /**
     * @param int $index
     * @throws DataItemNotFoundException
     * @return \Convo\Core\Media\IAudioFile
     */
    private function _getSong( $index)
    {
        $model      =   $this->_getQueryModel();
        $real_index =   $model['playlist'][$index];
        
        $this->_logger->info( 'Getting song ['.$index.'] with real index ['.$real_index.']');
        
        $query  =   $this->getWpQuery();
        $query->rewind_posts();
        while ( $query->have_posts())
        {
            $query->the_post();
            $post   =   $query->post;
            $this->_logger->debug( 'Checking page post ['.$post->post_title.'] index ['.$query->current_post.']['.$real_index.']');
            
            if ( $query->current_post === $real_index)
            {
                $meta       =   wp_get_attachment_metadata( $post->ID);
                //                 $this->_logger->debug( 'Post attachment meta ['.print_r( wp_get_attachment_metadata( $post->ID), true).']');
                
                $url        =   $this->_evaluateStringWithPost( $this->_songUrl, $post);
                $url        =   $url ? $url : wp_get_attachment_url( $post->ID);
                
                $song_title =   $this->_evaluateStringWithPost( $this->_songTitle, $post);
                $song_title =   $song_title ? $song_title : $meta['title'] ?? null;
                $song_title =   $song_title ? $song_title : $post->post_title;
                
                $artist     =   $this->_evaluateStringWithPost( $this->_artist, $post);
                $artist     =   $artist ? $artist : $meta['artist'] ?? null;
                $artist     =   is_numeric( $artist) || empty( $artist) ? ($meta['album'] ?? null) : $artist;
                
                $artwork    =   $this->_evaluateStringWithPost( $this->_artworkUrl, $post);
                $artwork    =   $artwork ? $artwork : get_the_post_thumbnail_url();
                $artwork    =   $artwork ? $artwork : $this->_evaluateStringWithPost( $this->_defaultSongImageUrl, $post);
                
                $background =   $this->_evaluateStringWithPost( $this->_backgroundUrl, $post);
                $this->_logger->info( 'Returning song ['.$url.']['.$song_title.']['.$artist.']['.$artwork.']['.$background.']');
                return new Mp3File( $url, $song_title, $artist, $artwork, $background);
            }
        }
        
        throw new DataItemNotFoundException( 'Could not find post by real index ['.$real_index.']');
    }
    
    // PERSISTANT MODEL NAVI
    private function _getQueryModel()
    {
        $params =   $this->getService()->getComponentParams( \Convo\Core\Params\IServiceParamsScope::SCOPE_TYPE_INSTALLATION, $this);
        $model  =   $params->getServiceParam( self::PARAM_NAME_QUERY_MODEL);
        
        if ( empty( $model)) {
            $this->_logger->info( 'There is no saved model. Going to create default one.');
            $model   =   [
                'playing' => false,
                'post_index' => 0,
                'loop_status' => empty( $this->_defaultLoop) ? false : $this->getService()->evaluateString( $this->_defaultLoop),
                'shuffle_status' => empty( $this->_defaultShuffle) ? false : $this->getService()->evaluateString( $this->_defaultShuffle),
                'playlist' => [],
                'song_offset' => 0,
                'arguments' => [],
            ];
            $this->_saveQueryModel( $model);
        }
        
        return $model;
    }
    
    private function _saveQueryModel( $model)
    {
        $this->_logger->info( 'Saving query model ['.print_r( $model, true).']['.$this.']');
        $params =   $this->getService()->getComponentParams( \Convo\Core\Params\IServiceParamsScope::SCOPE_TYPE_INSTALLATION, $this);
        $params->setServiceParam( self::PARAM_NAME_QUERY_MODEL, $model);
    }
    
    
    // UTIL
    public function __toString()
    {
        return parent::__toString().'['.$this->_id.']['.json_encode( $this->_baseFolderPath).']';
    }
}
