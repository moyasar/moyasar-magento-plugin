<?php

namespace Moyasar\Mysr\Model\Config;

use Magento\Config\Model\Config\Backend\File;
use Magento\Framework\App\Filesystem\DirectoryList;

class ApplePayCertificateFile extends File
{
    /**
     * Retrieve upload directory path
     *
     * @param string $uploadDir
     * @return string
     * @since 100.1.0
     */
    protected function getUploadDirPath($uploadDir)
    {
        $this->_mediaDirectory = $this->_filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        return $this->_mediaDirectory->getAbsolutePath($uploadDir);
    }

    protected function _getAllowedExtensions()
    {
        return [
            'pem',
            'key'
        ];
    }
}
