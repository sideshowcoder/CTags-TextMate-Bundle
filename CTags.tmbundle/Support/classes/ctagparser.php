<?php
/**
 */

// This is a very memory-hungry class.
ini_set('memory_limit', '1G');

/**
 */
class CTagParser
{
	/**
	 * Filename to open and parse.
	 *
	 * @var string $_filename
	 */
	protected $_filename;
	
	/**
	 * Tag cache, once the file has been parsed.
	 *
	 * @var array $_tags
	 */
	protected $_tags = array();
	
	/**
	 * Constructor.
	 *
	 * @param string $filename Optional filename to open and parse.
	 */
	public function __construct($filename = null)
	{
		if ($filename) {
			$this->setFilename($filename);
		}
	}
	
	/**
	 * Find a tag and return an array of where it's used.
	 *
	 * @param string $tag Tag to look for.
	 *
	 * @return mixed Array of tag info on success, or false.
	 */
	public function findTag($tag)
	{
		// Make sure the file has been parsed.
		$this->_parse();
		
		return array_key_exists($tag, $this->_tags)
			? $this->_tags[$tag]
			: false;
	}
	
	/**
	 * Filename setter.
	 *
	 * @param string $filename Path to the file to parse.
	 *
	 * @return void
	 */
	public function setFilename($filename)
	{
		$this->_filename = $filename;
		
		// Flush the tags when the filename is changed.
		$this->_tags = array();
		
		// Check for cached tags.
		$this->_loadCache();
	}
	
	/**
	 * Save tags to the cache.
	 *
	 * @return void
	 */
	protected function _saveCache()
	{
		$cacheFileName = $this->_filename . '.cache';
		file_put_contents($cacheFileName, serialize($this->_tags));
	}
	
	/**
	 * Load up any cached tags.
	 *
	 * @return void
	 */
	protected function _loadCache()
	{
		$cacheFileName = $this->_filename . '.cache';
		
		if (file_exists($cacheFileName)) {
			$startTime = microtime(true);
			$this->_tags = unserialize(file_get_contents($cacheFileName));
			$time = sprintf('%.3f', microtime(true) - $startTime);
			echo 'Loaded ', count($this->_tags), ' tag(s) from cache in ', $time, PHP_EOL;
		}
	}
	
	/**
	 * Parse the tags file if not already loaded, and cache its tags.
	 *
	 * @param bool $force Force the file to be parsed, even if it's cached.
	 *
	 * @return void
	 */
	protected function _parse($force = false)
	{
		$startTime = microtime(true);
		
		if (! $force && $this->_tags) {
			// There's already tags loaded, and we weren't forced.
			return;
		}
		
		static $pattern = '/^(?P<name>[^\t]+)\t(?P<file>[^\t]+)\t(?P<pattern>\/.*?\/;")\t(?P<type>[^\t]+)\t?(?P<other>.*)$/m';
		
		// Clear the currently-cached tags.
		$this->_tags = array();
		
		// Read the whole file (!).
		$file = file_get_contents($this->_filename);
		
		// Use preg_match_all to do the heavy lifting.
		if (! $count = preg_match_all($pattern, $file, $matches, PREG_SET_ORDER)) {
			throw new Exception('Failed to match regular expression: ' . $count);
		}
		
		foreach ($matches as $match) {
			// Create a tag object.
			$tag = (object) array(
				'name' => $match['name'],
				'file' => $match['file'],
				'pattern' => $match['pattern'],
				'type' => $match['type'],
				'other' => $match['other'],
			);
			
			if (substr($tag->other, 0, 5) == 'line:') {
				// Set the line number if it's in the 'other' property.
				$tag->line = (int) substr($tag->other, 5);
			}

			if (! array_key_exists($tag->name, $this->_tags)) {
				// Create an empty array if they key doesn't yet exist.
				$this->_tags[$tag->name] = array();
			}
			
			// Store this tag under its name.
			$this->_tags[$tag->name][] = $tag;
		}
		
		$time = sprintf('%.3f', microtime(true) - $startTime);
		echo 'Loaded ', count($this->_tags), ' tag(s) from file in ', $time, 'ms', PHP_EOL;
		
		// Save the tags to the cache.
		$this->_saveCache();
	}
}