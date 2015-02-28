#!/usr/bin/env php
<?php

/**
 * @author Chris Corbyn <chris@w3style.co.uk>
 *
 * Copyright © 2013-2014 Chris Corbyn.
 */

/* Generate releases in Github */

namespace Boris;

require __DIR__ . '/lib/autoload.php';

$args = getopt('hv:', array(
    'help',
    'version:'
));

if (count($args) != 1) {
    help();
    exit(1);
}

foreach ($args as $opt => $value) {
    switch ($opt) {
        case 'v':
        case 'version':
            version($value);
            exit(0);
        
        case 'h':
        case 'help':
            help();
            exit(0);
        
        default:
            unknown($opt);
            exit(1);
    }
}

function help()
{
    echo <<<HELP
Boris release generator script.

Usage:
  ./release.php --version 1.2    Create a release for v1.2
  ./release.php --help           Display this help message

HELP;
}

function version($newVersion)
{
    $token      = get_token();
    $user       = get_user();
    $repo       = get_repo();
    $oldVersion = Boris::VERSION;
    $phar       = "boris.phar";
    
    printf("Building version v%s...\n", $newVersion);
    
    printf("    Updating Boris::VERSION (%s) to %s...\n", $oldVersion, $newVersion);
    `perl -pi -e 's/$oldVersion/$newVersion/' lib/Boris/Boris.php README.md`;
    
    printf("    Committing changes...\n");
    `git commit -am "Version bump to $newVersion"`;
    
    printf("    Pushing changes upstream...\n");
    `git push`;
    
    printf("    Creating tag v%s...\n", $newVersion);
    `git tag -a "v$newVersion" -m "Auto-generated tag"`;
    
    printf("    Pushing tags upstream...\n");
    `git push --tags`;
    
    printf("    Creating release on github...\n");
    $response = `curl \
     -sL \
     -XPOST \
     -H "Authorization: token $token" \
     --data-binary '{"tag_name":"v$newVersion"}' \
     https://api.github.com/repos/$user/$repo/releases`;
    
    $json = json_decode($response, true);
    $id   = $json['id'];
    
    if (empty($id)) {
        printf("Failed.\n");
        printf("%s\n", $response);
        exit(1);
    }
    
    printf("    Building phar...\n");
    `box build`;
    
    printf("Uploading phar to GitHub...\n");
    `curl -XPOST \
     -sL \
     -H "Authorization: token $token" \
     -H "Content-Type: application/octet-stream" \
     --data-binary @$phar \
     https://uploads.github.com/repos/$user/$repo/releases/$id/assets?name=$phar`;
    
    printf("Done.\n");
}

function get_token()
{
    if (getenv('GITHUB_TOKEN')) {
        return getenv('GITHUB_TOKEN');
    } else {
        printf("Missing environment variable \$GITHUB_TOKEN\n");
        exit(1);
    }
}

function get_origin()
{
    $remotes = `git remote -v`;
    if (!preg_match('/^origin\s+(\S*?.git)\s+\(push\)/m', $remotes, $matches)) {
        printf("Unable to find origin in $remotes\n");
        exit(1);
    }
    return $matches[1];
}

function get_user()
{
    $origin = get_origin();
    if (!preg_match('#^.*?[/:]([^/]+)/([^/]+)\.git$#', $origin, $matches)) {
        printf("Don't know how to parse $origin\n");
        exit(1);
    }
    return $matches[1];
}

function get_repo()
{
    $origin = get_origin();
    if (!preg_match('#^.*?[/:]([^/]+)/([^/]+)\.git$#', $origin, $matches)) {
        printf("Don't know how to parse $origin\n");
        exit(1);
    }
    return $matches[2];
}
