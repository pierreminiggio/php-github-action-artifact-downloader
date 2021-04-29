<?php

use PierreMiniggio\GithubActionArtifactDownloader\GithubActionArtifactDownloader;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$downloader = new GithubActionArtifactDownloader();
$artifacts = $downloader->download(
    'token',
    'pierreminiggio',
    'remotion-test-github-action',
    56716993
);

var_dump($artifacts);
