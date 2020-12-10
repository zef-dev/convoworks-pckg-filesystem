<?php


namespace Convo\Pckg\Filesystem;


use Convo\Core\DataItemNotFoundException;
use Convo\Core\Params\IServiceParamsScope;
use Convo\Core\Media\Mp3File;
use Convo\Core\Workflow\AbstractBasicComponent;
use Convo\Core\Workflow\IMediaSourceContext;
use DirectoryIterator;
use wapmorgan\Mp3Info\Mp3Info;

class FilesystemMediaContext extends AbstractBasicComponent implements IMediaSourceContext
{
    const NOT_FOUND = 'not_found';

    private $_id;

    /** @var string */
    private $_baseFolderPath = "";

    /** @var string */
    private $_baseUrl = "";

    /** @var boolean */
    private $_shouldMovePonter = true;

    /**
     * @var array
     */
    private $_searchQuery = [];

    private $_availablePlaylistPaths = [];

    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct($properties)
    {
        parent::__construct($properties);
        $this->_id			    =	$properties['id'];
        $this->_baseFolderPath  =	$properties['mp3SourcePath'];
        $this->_baseUrl		    =	$properties['mp3SourceBasePath'];
    }

    /**
     * @inheritDoc
     */
    public function list(): iterable
    {
        if ($this->_shouldMovePonter) {
            $this->_setCurrentSongIndex(0);
        }
        $availableSongs = [];
        foreach ($this->_availablePlaylistPaths as $availablePlaylist) {
            $songsFolderPath = $availablePlaylist;
            foreach ($this->_getDirectoryFiles($songsFolderPath) as $song) {
                $songFile =  $songsFolderPath . DIRECTORY_SEPARATOR . $song;
                array_push($availableSongs, $songFile);
            }
        }
        $this->_getServiceParams()->setServiceParam('available_songs', $availableSongs);
        return $availableSongs;
    }

    /**
     * @inheritDoc
     */
    public function find(): iterable
    {
        $availableSongList = $this->list();
        $filteredSongsList = [];
        $targetPlaylist = null;
        $hasSearchedPlaylist = false;
        $searchTextSimilarityPercentage = 75;

        if (isset($this->_searchQuery['Playlist'])) {
            $hasSearchedPlaylist = true;
            if (str_contains(strtolower($this->_searchQuery['Playlist']), 'all')) {
                $targetPlaylist = null;
            } else if (str_contains(strtolower($this->_searchQuery['Playlist']), 'main')) {
                if (count($availableSongList) > 0) {
                    $songDirectoryName = $this->_prepareSong($availableSongList[0])->getDirectoryName();
                    $targetPlaylist = $songDirectoryName;
                }
            } else {
                $targetPlaylist = $this->_getTargetPlaylist($this->_searchQuery['Playlist']);
                if (self::NOT_FOUND === strtolower($targetPlaylist)) {
                    throw new DataItemNotFoundException("The specified playlist '" . $this->_searchQuery['Playlist'] . "' cant be found.");
                }
            }
        }

        foreach ($availableSongList as $songFile) {
            $songData = $this->_prepareSong($songFile);
            $this->_logger->info("Song data info [" . $songData->getDirectoryName() . "]");

            if ($targetPlaylist !== null) {
                $this->_logger->debug("Comparing [" . $targetPlaylist . "] and [" . $songData->getDirectoryName() . "]");
                if ($targetPlaylist !== $songData->getDirectoryName()) {
                    continue;
                }
            }

            if ($songData->isMetaDataAvailable()) {
                if (isset($this->_searchQuery['Artist']) && !isset($this->_searchQuery['Song'])) {
                    $percent = 100;
                    similar_text(strtolower($songData->getArtist()), strtolower($this->_searchQuery['Artist']), $percent);

                    if ($percent < $searchTextSimilarityPercentage) {
                        continue;
                    }
                } else if (isset($this->_searchQuery['Genre'])) {
                    $percent = 100;
                    similar_text(strtolower($songData->getGenre()), strtolower($this->_searchQuery['Genre']), $percent);

                    if ($percent < $searchTextSimilarityPercentage) {
                        continue;
                    }
                } else if (isset($this->_searchQuery['Artist']) && isset($this->_searchQuery['Song'])) {
                    $percentArtist = 100;
                    similar_text(strtolower($songData->getArtist()), strtolower($this->_searchQuery['Artist']), $percentArtist);
                    $percentSongTitle = 100;
                    similar_text(strtolower($songData->getSongTitle()), strtolower($this->_searchQuery['Song']), $percentSongTitle);
                    $percentAvg = ($percentArtist + $percentSongTitle) / 2;

                    if ($percentAvg < $searchTextSimilarityPercentage) {
                        continue;
                    }
                } else if (!isset($this->_searchQuery['Artist']) && isset($this->_searchQuery['Song'])) {
                    $percent = 100;
                    similar_text(strtolower($songData->getSongTitle()), strtolower($this->_searchQuery['Song']), $percent);

                    if ($percent < $searchTextSimilarityPercentage) {
                        continue;
                    }
                }
            } else {
                if (isset($this->_searchQuery['Artist']) && !isset($this->_searchQuery['Song'])) {
                    $this->_logger->debug("Search by file name for artist");
                    $artistFromFileName = explode("-", $songData->getFileName())[0];
                    $percent = 100;
                    similar_text(strtolower(trim($artistFromFileName)), strtolower($this->_searchQuery['Artist']), $percent);

                    if ($percent < $searchTextSimilarityPercentage) {
                        continue;
                    }
                } else if (isset($this->_searchQuery['Artist']) && isset($this->_searchQuery['Song'])) {
                    $this->_logger->debug("Search by file name for song by artist");
                    $artistFromFileName = explode("-", $songData->getFileName())[0];
                    $songFromFileName = explode("-", $songData->getFileName())[1];

                    $percentArtist = 100;
                    similar_text(strtolower(trim($artistFromFileName)), strtolower($this->_searchQuery['Artist']), $percentArtist);
                    $percentSongTitle = 100;
                    similar_text(strtolower(trim($songFromFileName)), strtolower($this->_searchQuery['Song']), $percentSongTitle);
                    $percentAvg = ($percentArtist + $percentSongTitle) / 2;

                    if ($percentAvg < $searchTextSimilarityPercentage) {
                        continue;
                    }
                } else if (!isset($this->_searchQuery['Artist']) && isset($this->_searchQuery['Song'])) {
                    $this->_logger->debug("Search by file name for song only");
                    $songFromFileName = explode("-", $songData->getFileName())[1];
                    $percent = 100;
                    similar_text(strtolower(trim($songFromFileName)), strtolower($this->_searchQuery['Song']), $percent);

                    if ($percent < $searchTextSimilarityPercentage) {
                        continue;
                    }
                }
            }
            array_push($filteredSongsList, $songFile);
        }

        if ($hasSearchedPlaylist && empty($filteredSongsList)) {
            throw new \Exception("The specified playlist '" . $this->_searchQuery['Playlist'] . "' is empty.");
        }

        $this->_getServiceParams()->setServiceParam('available_songs', $filteredSongsList);
        return $filteredSongsList;
    }

    public function setSearchQuery($searchQuery)
    {
        $this->_logger->debug("Setting query...");
        $this->_searchQuery = $searchQuery;
    }

    /**
     * @inheritDoc
     */
    public function current(): Mp3File
    {
        $song = new Mp3File('', '', [], '');
        if (isset($this->_getAvailableSongs()[$this->_getCurrentSongIndex()])) {
            $song = $this->_getAvailableSongs()[$this->_getCurrentSongIndex()];
        }
        return $this->_prepareSong($song);
    }

    /**
     * @inheritDoc
     */
    public function next(): Mp3File
    {
        $song = new Mp3File('', '', [], '');
        $songIndex = $this->_getNextSongIndex();

        if (!empty($this->_getAvailableSongs()[$songIndex])) {
            if ($this->_shouldMovePonter) {
                $this->_setCurrentSongIndex($songIndex);
            }
            $songFile = $this->_getAvailableSongs()[$songIndex];
            $song = $this->_prepareSong($songFile);
        }

        return $song;
    }

    /**
     * @inheritDoc
     */
    public function previous(): Mp3File
    {
        $song = new Mp3File('', '', [], '');
        $songIndex = $this->_getPreviousSongIndex();
        if (!empty($this->_getAvailableSongs()[$songIndex])) {
            if ($this->_shouldMovePonter) {
                $this->_setCurrentSongIndex($songIndex);
            }
            $songFile = $this->_getAvailableSongs()[$songIndex];
            $song = $this->_prepareSong($songFile);
        }

        return $song;
    }

    public function first(): Mp3File
    {
        if ($this->_shouldMovePonter) {
            $this->_setCurrentSongIndex(0);
        }
        return $this->_prepareSong($this->_getAvailableSongs()[0]);
    }

    public function last(): Mp3File
    {
        $songIndex = count($this->_getAvailableSongs()) - 1;
        if ($this->_shouldMovePonter) {
            $this->_setCurrentSongIndex($songIndex);
        }
        return $this->_prepareSong($this->_getAvailableSongs()[$songIndex]);
    }

    /**
     * @inheritDoc
     */
    public function setOffset($offset)
    {
        $this->_getServiceParams()->setServiceParam("current_song_offset", $offset);
    }

    /**
     * @inheritDoc
     */
    public function getOffset(): int
    {
        $currentSongOffsetFromInstallation = $this->_getServiceParams()->getServiceParam("current_song_offset");

        if (is_numeric($currentSongOffsetFromInstallation)) {
            return $currentSongOffsetFromInstallation;
        }

        return 0;
    }

    public function movePointerTo($index)
    {
        $this->_setCurrentSongIndex($index);
    }

    public function getPointerPosition(): int
    {
        return $this->_getCurrentSongIndex();
    }

    public function setLoopStatus($loopStatus)
    {
        $this->_getServiceParams()->setServiceParam('loop_status', $loopStatus);
    }

    public function getLoopStatus(): bool
    {
        return $this->_getServiceParams()->getServiceParam('loop_status') ? $this->_getServiceParams()->getServiceParam('loop_status') : false;
    }

    public function setShouldMovePointer($shouldMovePointer = true)
    {
        $this->_shouldMovePonter = $shouldMovePointer;
    }

    /**
     * @return string[]
     */
    private function _getAvailableSongs()
    {
        return $this->_getServiceParams()->getServiceParam("available_songs");
    }

    private function _setCurrentSongIndex($currentSongIndex) {
        $this->_getServiceParams()->setServiceParam("current_song_index", $currentSongIndex);
    }

    private function _getCurrentSongIndex() {
        $currentSongIndex = 0;
        $currentSongIndexFromInstallation = $this->_getServiceParams()->getServiceParam("current_song_index");

        if (is_numeric($currentSongIndexFromInstallation)) {
            $currentSongIndex = $currentSongIndexFromInstallation;
        }

        $this->_setCurrentSongIndex($currentSongIndex);
        return $currentSongIndex;
    }

    private function _getNextSongIndex() {
        return $this->_getCurrentSongIndex() + 1;
    }

    private function _getPreviousSongIndex() {
        return $this->_getCurrentSongIndex() - 1;
    }

    private function _prepareSong($songFile) {
        $fileMetaData = [];

        $this->_logger->debug("Song file path [" . $songFile . "]");
        $dirName = basename(dirname($songFile));
        $this->_logger->debug("Song dir path [" . $dirName . "]");
        $songUrl = $this->_baseUrl . rawurlencode($dirName) . "/" . rawurlencode(basename($songFile));
        if (in_array('mp3', explode("/", $this->_baseUrl)) && $dirName === 'mp3') {
            $songUrl = $this->_baseUrl . rawurlencode(basename($songFile));
        }

        try {
            $audio = new Mp3Info($songFile, true);
            $fileMetaData = $audio->tags;
        } catch (\Exception $e) {
            $this->_logger->warning($e->getMessage());
            return new Mp3File(basename($songFile), $songUrl, $fileMetaData, basename(dirname($songFile)));
        }

        $this->_logger->debug("Song file path [" . $songFile . "]");
        $this->_logger->debug("Song URL [" . $songUrl . "]");

        return new Mp3File(basename($songFile), $songUrl, $fileMetaData, basename(dirname($songFile)));
    }

    private function _getServiceParams() {
        return $this->getService()->getServiceParams(IServiceParamsScope::SCOPE_TYPE_INSTALLATION);
    }

    private function _evaluateSting($data) {
        $service = $this->getService();
        return $service->evaluateString($data);
    }

    private function _getDirectoryDirectories($folderPath) {
        $directoryDirectories = [];
        foreach(new DirectoryIterator($folderPath) as $item) {
            if (!$item->isDot() && $item->isDir()) {
                array_push($directoryDirectories, $item->getFilename());
            }
        }
        return $directoryDirectories;
    }

    private function _getDirectoryFiles($folderPath) {
        $directoryFiles = [];
        foreach(new DirectoryIterator($folderPath) as $item) {
            if (!$item->isDot() && $item->isFile()) {
                array_push($directoryFiles, $item->getFilename());
            }
        }
        return $directoryFiles;
    }

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->_id			    =	$this->_evaluateSting($this->_id);
        $this->_baseFolderPath  =	$this->_evaluateSting($this->_baseFolderPath);
        $this->_baseUrl		    =	$this->_evaluateSting($this->_baseUrl);
        // check already set folderBase path if is right dir in the filesystem
        if (!is_dir($this->_baseFolderPath)) {
            // when the path is not a valid directory just return the playlist object with initial values
            $this->_logger->warning("[" . $this->_baseFolderPath . "] is not a valid folder.");
            $this->_getServiceParams()->setServiceParam('available_playlists', []);
            throw new \Exception("'" . $this->_baseFolderPath . "' is not a valid folder.");
        }

        // set available playlists variable
        $this->_availablePlaylistPaths = [$this->_baseFolderPath];
        $this->_logger->debug("Going to print available playlists...");
        $this->_logger->debug("Printing playlist [" . $this->_availablePlaylistPaths[0] . "]");
        foreach ($this->_getDirectoryDirectories($this->_baseFolderPath) as $playlistFolder) {
            $playlist = $this->_baseFolderPath . $playlistFolder;
            $this->_logger->debug("Printing playlist [" . $playlist . "]");
            array_push($this->_availablePlaylistPaths, $playlist);
        }
    }

    private function _getTargetPlaylist($searchQueryPlaylist) {
        $targetPlaylist = null;
        $searchTextSimilarityPercentage = 75;
        $playlists = $this->_getDirectoryDirectories($this->_baseFolderPath);
        $playlistCandidates = [];

        foreach ($playlists as $playlist) {
            $percent = 0;
            similar_text(strtolower($playlist), strtolower($searchQueryPlaylist), $percent);
            array_push($playlistCandidates, ["playlist" => $playlist, "score" => $percent]);
        }

        usort($playlistCandidates, function ($first, $second) {
            return $first["score"] < $second["score"];
        });

        if ($playlistCandidates[0]["score"] > $searchTextSimilarityPercentage) {
            $candidate = $playlistCandidates[0]["playlist"];
            $targetPlaylist = $candidate;
        } else {
            $targetPlaylist = self::NOT_FOUND;
        }

        return $targetPlaylist;
    }

    /**
     * @inheritDoc
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @inheritDoc
     */
    public function getComponent()
    {
        return $this;
    }
}
