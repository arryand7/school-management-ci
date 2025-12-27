<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Media_storage
{

    private $_CI;
    private $last_error = '';

    public function __construct()
    {
        $this->_CI = &get_instance();
        $this->_CI->load->library('customlib');

    }

    public function getLastError()
    {
        return $this->last_error;
    }

    public function fileupload($media_name, $upload_path = "")
    {
        $this->last_error = '';

        if (!isset($_FILES[$media_name]) || !is_array($_FILES[$media_name])) {
            $this->last_error = 'File upload tidak ditemukan.';
            return null;
        }

        if ($_FILES[$media_name]['error'] !== UPLOAD_ERR_OK) {
            $this->last_error = $this->uploadErrorMessage($_FILES[$media_name]['error']);
            log_message('error', 'Upload error for ' . $media_name . ': ' . $this->last_error);
            return null;
        }

        if (!is_uploaded_file($_FILES[$media_name]['tmp_name'])) {
            $this->last_error = 'File upload tidak valid.';
            log_message('error', 'Invalid uploaded file for ' . $media_name);
            return null;
        }

        $name        = basename($_FILES[$media_name]['name']);
        $file_name   = time() . "-" . uniqid(rand()) . "!" . $name;
        $normalized_path = $this->normalizeUploadPath($upload_path);
        $base_path       = rtrim($this->_CI->customlib->getFolderPath(), "/\\") . DIRECTORY_SEPARATOR;
        $target_dir      = $base_path . $normalized_path;

        if ($this->isRelativeUploadsPath($normalized_path)) {
            $fcp_base = rtrim(FCPATH, "/\\") . DIRECTORY_SEPARATOR;
            if (strpos($target_dir, $fcp_base) !== 0) {
                $target_dir = $fcp_base . $normalized_path;
            }
        }

        if (!is_dir($target_dir) && !@mkdir($target_dir, 0755, true)) {
            $this->last_error = 'Folder upload tidak bisa dibuat. Periksa permission.';
            log_message('error', 'Unable to create upload directory: ' . $target_dir);
            return null;
        }

        if (!is_writable($target_dir)) {
            @chmod($target_dir, 0777);
        }

        if (!is_writable($target_dir)) {
            $this->last_error = 'Folder upload tidak bisa ditulis. Periksa permission.';
            log_message('error', 'Upload directory is not writable: ' . $target_dir);
            return null;
        }

        $destination = $target_dir . $file_name;

        if (move_uploaded_file($_FILES[$media_name]["tmp_name"], $destination)) {
            return $file_name;
        }

        $this->last_error = 'Gagal menyimpan file ke folder upload.';
        log_message('error', 'Failed to move upload to ' . $destination);
        return null;
    }

    public function filedownload($file_name, $download_path = "")
    {

        $file_url           = $this->_CI->customlib->getFolderPath() . $download_path . "/" . $file_name;
        $download_file_name = substr($file_name, (strpos($file_name, '!') + 1));
        $this->_CI->load->helper('download');
        $data = file_get_contents($file_url);
        force_download($download_file_name, $data);

    }

    public function fileview($file_name)
    {
        if (!IsNullOrEmptyString($file_name)) {

            $download_file_name = substr($file_name, (strpos($file_name, '!') + 1));
            return $download_file_name;
        }
        return null;

    }

    public function getImageURL($file_name)
    {
        if (!IsNullOrEmptyString($file_name)) {

            $download_file_name = $this->_CI->customlib->getBaseUrl() . $file_name . img_time();
            return $download_file_name;
        }
        return null;

    }

    public function filedelete($file_name, $path = "")
    {
        if (!IsNullOrEmptyString($file_name)) {

            $url = $this->_CI->customlib->getFolderPath() . $path . "/" . $file_name;

            if (file_exists($url)) {

                if (unlink($url)) {
                    return true;
                }

            }
        }

        return false;
    }

    private function normalizeUploadPath($upload_path)
    {
        $clean = str_replace('\\', '/', $upload_path);
        $clean = preg_replace('#/+#', '/', $clean);
        if (strpos($clean, './') === 0) {
            $clean = substr($clean, 2);
        }
        $clean = ltrim($clean, '/');
        if ($clean !== '' && substr($clean, -1) !== '/') {
            $clean .= '/';
        }
        return $clean;
    }

    private function isRelativeUploadsPath($normalized_path)
    {
        return $normalized_path !== '' && strpos($normalized_path, 'uploads/') === 0;
    }

    private function uploadErrorMessage($error_code)
    {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File melebihi batas upload_max_filesize.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File melebihi batas MAX_FILE_SIZE.';
            case UPLOAD_ERR_PARTIAL:
                return 'File hanya terupload sebagian.';
            case UPLOAD_ERR_NO_FILE:
                return 'Tidak ada file yang diupload.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Folder temporary upload tidak ditemukan.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Gagal menulis file ke disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload dihentikan oleh ekstensi PHP.';
            default:
                return 'Terjadi error saat upload file.';
        }
    }

}
