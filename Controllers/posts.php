<?php

require_once "vendor.php";
require_once "functions.php";

function route($method, $urlData, $formData)
{
    switch ($method) {
        case 'GET':
            Get($urlData);
            break;
        case 'POST':
            Post($urlData, $formData);
            break;
        case 'PATCH':
            Patch($urlData, $formData);
            break;
        case 'DELETE':
            Delete($urlData);
            break;
        default:
            http_response_code(501);
            break;
    }
}

function Get($urlData)
{
    global $connect;

    switch (sizeof($urlData)) {
        case 0:
            $request = "SELECT * FROM `post`";
            $posts = $connect->query($request);
            PrintJson($posts);
            break;
        case 1:
            if (is_numeric($urlData[0])) {
                $postId = $urlData[0];
                $request = "SELECT * FROM `post` WHERE `post`.`Id` = $postId";
                $posts = $connect->query($request);
                PrintJson($posts);
            } else
                http_response_code(501);
            break;
        default:
            http_response_code(501);
            break;
    }
}

function Post($urlData, $formData)
{
    $headers = getallheaders();
    global $connect;

    if (isset($headers["Authorization"])) {
        $token = str_replace("Bearer ", "", $headers["Authorization"]);

        $userId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

        if (isset($userId)) {

            switch (sizeof($urlData)) {
                case 0:
                    $text = $formData->Text;

                    $connect->query("INSERT INTO `post` (`Id`, `Text`, `OwnerId`) 
                               VALUES (NULL, '$text', $userId)");

                    break;
                default:
                    http_response_code(501);
                    break;
            }
        } else {
            http_response_code(401);
            exit();
        }
    }
}

function Patch($urlData, $formData)
{
    $headers = getallheaders();
    global $connect;
    if (isset($urlData[0]) && is_numeric($urlData[0])) {
        $postId = $urlData[0];
        $text = $formData->Text;

        if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
        else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

        if ($generalAccessLevel == ADMIN_ACCESS_LEVEL || $generalAccessLevel == MODERATOR_ACCESS_LEVEL) {

            $connect->query("UPDATE `post` SET `Text` = '$text' WHERE `post`.`Id` = '$postId'");

        } else if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
            $token = str_replace("Bearer ", "", $headers["Authorization"]);
            $userId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

            $post = mysqli_fetch_array($connect->query("SELECT * FROM `post` WHERE `post`.`Id` = '$postId'"))[0];

            if ($userId == $post[OwnerId]) {
                $connect->query("UPDATE `post` SET `Text` = '$text' WHERE `post`.`Id` = '$postId'");
            } else {
                http_response_code(401);
                exit();
            }
        } else {
            http_response_code(401);
            exit();
        }
    } else {
        http_response_code(501);
        exit();
    }

}

function Delete($urlData)
{
    $headers = getallheaders();
    global $connect;
    if (is_numeric($urlData[0])) {
        $postId = $urlData[0];

        if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
        else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

        if ($generalAccessLevel == ADMIN_ACCESS_LEVEL || $generalAccessLevel == MODERATOR_ACCESS_LEVEL) {

            $connect->query("DELETE FROM `post` WHERE Id = '$postId'");

        } else if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
            $token = str_replace("Bearer ", "", $headers["Authorization"]);
            $userId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

            $post = mysqli_fetch_array($connect->query("SELECT * FROM `post` WHERE `post`.`Id` = '$postId'"))[0];

            if ($userId == $post[OwnerId]) {
                $connect->query("DELETE FROM `post` WHERE Id = '$postId'");
            } else {
                http_response_code(401);
                exit();
            }
        } else {
            http_response_code(401);
            exit();
        }
    } else {
        http_response_code(501);
        exit();
    }
}
