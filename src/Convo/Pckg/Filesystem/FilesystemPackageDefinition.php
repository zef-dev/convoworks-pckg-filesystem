<?php declare(strict_types=1);

namespace Convo\Pckg\Filesystem;

use Convo\Core\Factory\AbstractPackageDefinition;

class FilesystemPackageDefinition extends AbstractPackageDefinition
{
	const NAMESPACE = 'convo-filesystem';

	public function __construct(
		\Psr\Log\LoggerInterface $logger
	)
	{
		parent::__construct($logger, self::NAMESPACE, __DIR__);

        $this->addTemplate( $this->_loadFile(__DIR__ . '/convo-custom-music-player.template.json'));
	}

	protected function _initDefintions()
	{
	    return [
            new \Convo\Core\Factory\ComponentDefinition(
                $this->getNamespace(),
                '\Convo\Pckg\Filesystem\FilesystemMediaContext',
                'Media source context',
                'Setup params for MP3 files',
                array(
                    'id' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => 'search_media',
                        'name' => 'Context ID',
                        'description' => 'Unique ID by which this context is referenced',
                        'valueType' => 'string'
                    ),
                    'search' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Search by',
                        'description' => 'Search phrase to match songs by any available criteria',
                        'valueType' => 'string'
                    ),
                    'search_folders' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Search folders',
                        'description' => 'Search phrase to match the whole folders only (like playlists)',
                        'valueType' => 'string'
                    ),
                    'base_path' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'MP3 folder path',
                        'description' => 'Folder of MP3s',
                        'valueType' => 'string'
                    ),
                    'base_url' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Base URL',
                        'description' => 'Public URL to expose songs from',
                        'valueType' => 'string'
                    ),
                    'background_url' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Background image',
                        'description' => 'Background image url. Can be expression which will be evaluated in the service context.',
                        'valueType' => 'string'
                    ),
                    'default_song_image_url' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Default song image',
                        'description' => 'Default image for song artwork. Can be expression which will be evaluated in the service context.',
                        'valueType' => 'string'
                    ),
                    'default_loop' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Default loop status',
                        'description' => 'Empty (false) or expression (boolean) to have initial player loop state',
                        'valueType' => 'string'
                    ),
                    'default_shuffle' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Default shuffle status',
                        'description' => 'Empty (false) or expression (boolean) to have initial player shuffle state',
                        'valueType' => 'string'
                    ),
                    '_preview_angular' => array(
                        'type' => 'html',
                        'template' => '<div class="code">' .
                        '<span class="statement">Filesystem Media </span> <b>[{{ contextElement.properties.id }}]</b>' .
                        '</div>'
                    ),
                    '_help' =>  array(
                        'type' => 'file',
                        'filename' => 'filesystem-media-context.html'
                    ),
                    '_interface' => '\Convo\Core\Workflow\IServiceContext',
                    '_workflow' => 'datasource'
                )
            ),
            new \Convo\Core\Factory\ComponentDefinition(
                $this->getNamespace(),
                '\Convo\Pckg\Filesystem\Elements\JsonReader',
                'JSON Reader',
                'URL',
                array(
                    'scope_type' => array(
                        'editor_type' => 'select',
                        'editor_properties' => array(
                            'options' => array('session' => 'Session', 'installation' => 'Installation', 'request' => 'Request'),
                        ),
                        'defaultValue' => 'session',
                        'name' => 'Scope type',
                        'description' => 'Id under which parameters are stored',
                        'valueType' => 'string'
                    ),
                    'url' => array(
                        'editor_type' => 'ssml',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'URL',
                        'valueType' => 'string'
                    ),
                    'var' => array(
                        'editor_type' => 'ssml',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'VAR',
                        'valueType' => 'string'
                    ),
                    'decode' => array(
                        'editor_type' => 'boolean',
                        'editor_properties' => array(),
                        'defaultValue' => false,
                        'name' => 'Decode',
                        'description' => 'Decode special html characters',
                        'valueType' => 'boolean'
                    ),
                    '_preview_angular' => array(
                        'type' => 'html',
                        'template' => '<div class="we-say"><b>Reading: {{component.properties.url}}</b></div>'
                    ),
                    '_help' =>  array(
                        'type' => 'file',
                        'filename' => 'json-reader.html'
                    ),
                    '_workflow' => 'read',
                )
            ),
            new \Convo\Core\Factory\ComponentDefinition(
                $this->getNamespace(),
                '\Convo\Pckg\Filesystem\Elements\FileReader',
                'File Reader',
                'Folders and Files',
                array(
                    'basePath' => array(
                        'editor_type' => 'ssml',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'basePath',
                        'valueType' => 'string'
                    ),
                    'mode' => array(
                        'editor_type' => 'select',
                        'editor_properties' => array(
                            'options' => array('folders' => 'Folders', 'files' => 'Files'),
                        ),
                        'defaultValue' => 'folders',
                        'name' => 'Mode',
                        'description' => '',
                        'valueType' => 'string'
                    ),
                    'var' => array(
                        'editor_type' => 'ssml',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'VAR',
                        'valueType' => 'string'
                    ),
                    '_preview_angular' => array(
                        'type' => 'html',
                        'template' => '<div class="we-say"><b>Reading {{component.properties.mode}} {{component.properties.basePath}}</b></div>'
                    ),
                    '_help' =>  array(
                        'type' => 'file',
                        'filename' => 'file-reader.html'
                    ),
                    '_workflow' => 'read',
                )
            ),
		];
	}
}
