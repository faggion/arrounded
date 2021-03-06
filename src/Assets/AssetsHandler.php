<?php
namespace Arrounded\Assets;

class AssetsHandler
{
    /**
     * @type array
     */
    private $collections;

    /**
     * @param array $collections
     */
    public function __construct(array $collections)
    {
        $this->collections = $collections;
    }

    /**
     * Display a collection of styles
     *
     * @param array|string $collection
     *
     * @return string
     */
    public function styles($collection)
    {
        return $this->createBuild($collection, 'css');
    }

    /**
     * Display a collection of scripts
     *
     * @param array|string $collection
     *
     * @return string
     */
    public function scripts($collection)
    {
        return $this->createBuild($collection, 'js');
    }

    //////////////////////////////////////////////////////////////////////
    ////////////////////////////// HELPERS ///////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * Get a collection from the declared ones
     *
     * @param string|array $collection
     * @param string       $type
     *
     * @return array
     */
    protected function getCollection($collection, $type)
    {
        if (!is_string($collection)) {
            return $collection;
        }

        // Expand paths
        $assets     = [];
        $collection = array_get($this->collections, $collection.'.'.$type, []);
        $negated    = array_filter($collection, function ($asset) {
            return substr($asset, 0, 1) == '!';
        });
        $collection = array_diff($collection, $negated);

        foreach ($collection as $asset) {
            $asset  = $this->expandPaths($asset, $negated);
            $assets = array_merge($assets, $asset);
        }

        return array_unique($assets);
    }

    /**
     * Create the HTML for a build
     *
     * @param string|array $collection
     * @param string       $type
     *
     * @return string
     */
    protected function createBuild($collection, $type)
    {
        $assets = $this->getCollection($collection, $type);

        // Create HTML tags
        $html    = [];
        $pattern = $type === 'css' ? '<link rel="stylesheet" href="%s">' : '<script src="%s"></script>';
        $html[]  = sprintf('<!-- build:%s builds/%s/%s.%s -->', $type, $type, $collection, $type);
        foreach ($assets as $asset) {
            $html[] = sprintf($pattern, $asset);
        }
        $html[] = '<!-- endbuild -->';

        return implode(PHP_EOL, $html);
    }

    /**
     * Expand the paths in an asset
     *
     * @param string $asset
     * @param array  $negated
     *
     * @return array
     */
    protected function expandPaths($asset, array $negated = array())
    {
        // If no wildcard, return as is
        if (strpos($asset, '*') === false) {
            return [$asset];
        }

        // Expand paths via glob:
        // This looks really dirty but ironically is a lot cleaner than using
        // a RecursiveDirectoryIterator as this blinds the class form having
        // to know what is and isn't a folder in the path, allows to expand
        // paths like folder/**/folder/**/*.js
        $asset = str_replace('**/*', '{*,*/*,*/*/*,*/*/*/*,*/*/*/*/*}', $asset);
        $asset = trim($asset, '/');
        $asset = public_path($asset);
        $asset = glob($asset, GLOB_BRACE);

        // Remove public prefix
        $asset = array_map(function ($asset) {
            return str_replace(public_path(), null, $asset);
        }, $asset);

        $asset = array_filter($asset, function ($file) use ($negated) {
            return !in_array('!'.$file, $negated);
        });

        // Sort assets
        sort($asset);

        return $asset;
    }
}
