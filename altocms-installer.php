<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Alto CMS Unpacker and Installer
 */
class Installer {

    const VERSION = '1.0.0';

    protected $sTargetDir;
    protected $sExecUrl;
    protected $sExecDir;
    protected $sExecPhp;
    protected $sUnpackDir;

    public function __construct() {

        $this->sExecUrl = $this->_urlScheme() . $_SERVER['SERVER_NAME'];
        if (isset($_SERVER['REQUEST_URI'])) {
            $sPath = $_SERVER['REQUEST_URI'];
            $this->sExecPhp = $this->sExecUrl . $sPath;
            $sPath = trim(dirname($sPath), '/\\');
            if ($sPath) {
                $this->sExecUrl .= $sPath . '/';
            } else {
                $this->sExecUrl .= '/';
            }
        } else {
            $this->sExecUrl .= '/';
            $this->sExecPhp = $this->sExecUrl;
        }
        $this->sExecDir = $this->_normPath(__DIR__ . '/');
        $this->sTargetDir = $this->sExecDir;

        if ($sDir = $this->_getParam('target')) {
            $this->sTargetDir .= trim($sDir, '/\\') . '/';
        }

        $this->sUnpackDir = $this->sExecDir . '_unpack/';

        if (ob_get_level() == 0) ob_start();
        $this->_out('<!DOCTYPE html><html><head></head><body>');
    }

    public function __destruct() {

        $this->_out('</body></html>');
        ob_end_flush();
    }

    /**
     * Immediate strings in browser
     */
    protected function _out() {

        foreach (func_get_args() as $sStr) {
            //echo str_replace(' ', '&nbsp;', $sStr);
            echo $sStr;
        }
        echo str_pad('',4096) . "\n";

        ob_flush();
        flush();
    }

    /**
     * Immediate string line in browser
     */
    protected function _outLn() {

        $sOut = '';
        foreach (func_get_args() as $sStr) {
            $sOut .= $sStr;
        }
        $this->_out($sOut . '<br>');
    }

    /**
     * Output error message and exit
     *
     * @param $sMessage
     */
    protected function _error($sMessage) {

        $this->_outLn();
        $this->_outLn('ERROR: ' . $sMessage);
        exit;
    }

    protected function _getParam($sName) {

        if (isset($_GET[$sName])) {
            return $_GET[$sName];
        }
        return null;
    }

    public function _goInstall() {

        $sFile = $this->sTargetDir . 'install/index.php';
        if (is_file($sFile)) {
            $sPath = $this->_localPath($sFile, $this->sExecDir);
            if ($sPath) {
                $sUrl = $this->sExecUrl . $sPath;
                $this->_outLn('To continue installation click here: <a href="' . $sUrl . '">' . $sUrl . '</a>');
            }
        }
    }

    protected function _urlScheme() {

        $sResult = 'http';
        if(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
            $sResult = 'https';
        } elseif (isset($_SERVER['HTTP_SCHEME']) && strtolower($_SERVER['HTTP_SCHEME']) == 'https') {
            $sResult = 'https';
        } elseif(isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
            $sResult = 'https';
        }
        $sResult .= '://';

        return $sResult;
    }

    protected function _normPath($sPath) {

        return str_replace('\\', '/', $sPath);
    }

    /**
     * Extract local path
     *
     * @param string $sPath
     * @param string $sRoot
     *
     * @return bool|string
     */
    protected function _localPath($sPath, $sRoot) {

        $sPath = $this->_normPath($sPath);
        $sRoot = $this->_normPath($sRoot);
        if (strpos($sPath, $sRoot) == 0) {
            return substr($sPath, strlen($sRoot));
        }
        return false;
    }

    /**
     * Recursively read directory
     *
     * @param string $sDir
     * @param bool   $bRecursive
     *
     * @return string[]
     */
    protected function _readDir($sDir, $bRecursive = true) {

        if (substr($sDir, -1) != '/') {
            $sDir .= '/';
        }
        $aFiles = glob($sDir . '{,.}*', GLOB_BRACE);

        $aFiles = array_filter($aFiles, function($sFile){return basename($sFile) != '.' && basename($sFile) != '..';});
        if (!$bRecursive || !$aFiles) {
            return $aFiles;
        }

        $aResult = array();
        foreach($aFiles as $sFile) {
            $aResult[] = $sFile;
            if (is_dir($sFile)) {
                $aSubResult = $this->_readDir($sFile);
                if ($aSubResult) {
                    $aResult = array_merge($aResult, $aSubResult);
                }
            }
        }
        return $aResult;
    }

    /**
     * Check directory and create it if not exists
     *
     * @param $sDir
     *
     * @return bool
     */
    protected function _checkDir($sDir) {

        if (!is_dir($sDir)) {
            @mkdir($sDir, 0755, true);
        }
        if (!is_dir($sDir)) {
            $this->_error('Cannot make dir ' . $sDir);
        }
        return is_dir($sDir);
    }

    /**
     * Recursively clear the directory
     *
     * @param string $sDir
     *
     * @return bool
     */
    protected function _clearDir($sDir) {

        $aFiles = $this->_readDir($sDir, false);
        foreach ($aFiles as $sFile) {
            if (is_file($sFile)) {
                @unlink($sFile);
            }
        }
        foreach ($aFiles as $sFile) {
            if (is_dir($sFile)) {
                $this->_removeDir($sFile);
            }
        }
        $aFiles = $this->_readDir($sDir);
        return empty($aFiles) ? true : false;
    }

    /**
     * Remove directory with all its subdirectories
     *
     * @param $sDir
     */
    protected function _removeDir($sDir) {

        if ($this->_clearDir($sDir)) {
            @rmdir($sDir);
        }
    }

    /**
     * Copy file
     *
     * @param string $sSource
     * @param string $sTarget
     *
     * @return bool
     */
    protected function _copyFile($sSource, $sTarget) {

        $sDirName = dirname($sTarget);
        $this->_checkDir($sDirName);

        $bResult = @copy($sSource, $sTarget);
        if (!$bResult) {
            $this->_error('Cannot copy file from "' . $sSource . '" to "' . $sTarget . '"');
        }
        return $bResult ? $sTarget : false;
    }

    /**
     * Recursively copy directory
     *
     * @param string $sDirSrc
     * @param string $sDirTrg
     *
     * @return bool
     */
    protected function _copyDir($sDirSrc, $sDirTrg) {

        $sDirTrg = $this->_normPath($sDirTrg . '/');
        $aSource = $this->_readDir($sDirSrc);
        $nTotal = sizeof($aSource);
        $nInc = 50 / $nTotal;
        $this->_out('   .');
        $nStep = 0;
        foreach ($aSource as $iIndex => $sSource) {
            if (round($iIndex * $nInc) > $nStep) {
                $this->_out('.');
                $nStep = round($iIndex * $nInc);
            }
            $sTarget = $this->_localPath($sSource, $sDirSrc);
            if ($sTarget) {
                if (is_file($sSource)) {
                    $bResult = $this->_copyFile($sSource, $sDirTrg . $sTarget);
                    if (!$bResult) {
                        $this->_error('Cannot copy file ' . $sSource);
                    }
                } elseif (is_dir($sSource)) {
                    $sDirName = $sDirTrg . $sTarget;
                    $this->_checkDir($sDirName);
                }
            } else {
                $this->_error('Cannot copy file ' . $sSource);
            }
        }
        $this->_outLn();

        return true;
    }

    /**
     * Seek root directory in unpacked archive
     *
     * @param string $sDir
     *
     * @return string
     */
    protected function _seekRoot($sDir) {

        if (substr($sDir, -1) !== '/') {
            $sDir .= '/';
        }
        if (is_file($sDir . 'index.php')) {
            return $sDir . 'index.php';
        }

        $aDirs = glob($sDir . '*', GLOB_ONLYDIR);
        $sRootFile = '';
        if ($aDirs) {
            foreach ($aDirs as $sDir) {
                if ($sRootFile = $this->_seekRoot($sDir . '/')) {
                    return $sRootFile;
                }
            }
        }
        return $sRootFile;
    }

    /**
     * Get version of Alto CMS in unpacked archive
     *
     * @param $sDir
     *
     * @return bool
     */
    protected function _getVersion($sDir) {

        $sFile = $sDir . '/engine/loader.php';
        if (is_file($sFile)) {
            $sText = file_get_contents($sFile);
            if ($sText && preg_match('/define\(\'ALTO_VERSION\', \'([^\']+)\'\);/', $sText, $aM)) {
                return $aM[1];
            }
        }
        return false;
    }

    /**
     * Unpack zip-archive
     *
     * @param string $sPackFile
     * @param string $sTargetDir
     *
     * @return bool
     */
    protected function _unpack($sPackFile, $sTargetDir) {

        $sUnpackDir = $this->sUnpackDir;
        $zip = new ZipArchive;
        if ($zip->open($sPackFile) === true) {
            if (!$this->_checkDir($sUnpackDir)) {
                $this->_error('Cannot make dir ' . $sUnpackDir);
                return false;
            }
            if (!$this->_clearDir($sUnpackDir)) {
                $this->_error('Cannot clear dir ' . $sUnpackDir);
                return false;
            }
            $this->_out('Unpack archive, please wait...');
            if (!$zip->extractTo($sUnpackDir)) {
                $this->_outLn();
                $this->_error('Cannot unpack file ' . $sPackFile);
                return false;
            } else {
                $this->_outLn(' - ok');
                $this->_out('Find root dir');
                $sRootFile = $this->_seekRoot($sUnpackDir . '/');
                if (!$sRootFile) {
                    $this->_error('Cannot find root of archive');
                    return false;
                }
                $sPackSrc = dirname($sRootFile);
                $sVersion = $this->_getVersion($sPackSrc);
                if (!$sVersion) {
                    $this->_outLn();
                    $this->_error('Version not defined');
                }
                $this->_outLn(' - Alto CMS v.' . $sVersion);

                $this->_out('Copy files');
                $this->_copyDir($sPackSrc, $sTargetDir);
            }
            $zip->close();
            $this->_removeDir($sUnpackDir);
        } else {
            $this->_error('Cannot open file ' . $sPackFile);
        }
        return true;
    }

    /**
     * Execute installation from the file
     *
     * @param string $sFile
     */
    public function execFile($sFile) {

        $sFile = $this->sExecDir . $sFile;
        $this->_unpack($sFile, $this->sTargetDir);
        $this->_outLn('Done');

        $this->_goInstall();
    }

    /**
     * Show help text
     */
    public function execHelp() {

        $this->_outLn();
        $this->_outLn('Usage: <strong>' . $this->sExecUrl . '?file=file_name.zip</strong>');
        $this->_outLn();
        $aFiles = glob($this->sExecDir . '*.zip');
        if ($aFiles) {
            foreach($aFiles as $iIndex => $sFile) {
                if (!is_file($sFile)) {
                    unset($aFiles[$iIndex]);
                }
            }
            if ($aFiles) {
                $this->_outLn('Or click link below:');
                $this->_out('<ul>');
                foreach($aFiles as $sFile) {
                    $sFile = basename($sFile);
                    $this->_outLn(
                        '<li>'
                        . '<a href="' . $this->sExecPhp . '?file=' . $sFile . '">'
                        . $sFile . '</a>'
                        . '</li>'
                    );
                }
                $this->_out('<ul>');
            }
        }
    }

    /**
     * Execute installation
     */
    public function exec() {

        $this->_outLn('** Alto CMS Installer **');

        if ($sFile = $this->_getParam('file')) {
            $this->execFile($sFile);
        } else {
            $this->execHelp();
        }
    }

    /**
     * Run installation
     */
    static public function run() {

        $app = new self();
        $app->exec();
    }

}

Installer::run();

// EOF