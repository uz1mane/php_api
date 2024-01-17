<?php

require_once "vendor.php";
require_once "functions.php";

function route($method, $urlData, $formData)
{
    switch ($method) {
        case 'GET':
            Get($urlData, $formData);
            break;
        case 'POST':
            Post($urlData, $formData);
            break;
        case 'DELETE':
            Delete($urlData);
            break;
        default:
            http_response_code(501);
            break;
    }
}

function Get($urlData, $formData)
{
    $headers = getallheaders();
    global $connect;

    if (isset($headers["Authorization"])) {
        $token = str_replace("Bearer ", "", $headers["Authorization"]);

        $userId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

        if (isset($userId)) {
            $photos = mysqli_fetch_array($connect->query("SELECT * FROM `photo` WHERE `photo`.`OwnerId` = '$userId'"));

            if(isset($photos)){
                PrintJson($photos);
            }
        } else {
            http_response_code(401);
            exit();
        }
    } else {
        http_response_code(401);
        exit();
    }
}

function Post($urlData, $formData)
{
    switch (sizeof($urlData)) {
        case 0:
            AddPhoto();
            break;
        default:
            http_response_code(501);
            break;
    }
}

function Delete($urlData)
{
    $headers = getallheaders();
    global $connect;
    if (is_numeric($urlData[0])) {
        $photoId = $urlData[0];

        if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
        else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

        if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {
            $request = "DELETE FROM `photo` WHERE Id = '$photoId'";
            $connect->query($request);
        } else if ($generalAccessLevel != UNAUTHORIZED_ACCESS_LEVEL) {
            $token = str_replace("Bearer ", "", $headers["Authorization"]);
            $userId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];
            $photoOwnerId = mysqli_fetch_array($connect->query("SELECT OwnerId FROM `photo` WHERE `photo`.`Id` = '$photoId'"))[0];

            if ($userId === $photoOwnerId) {
                $request = "DELETE FROM `photo` WHERE Id = '$photoId'";
                $connect->query($request);
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

function AddPhoto()
{
    $headers = getallheaders();
    global $connect;


    if (isset($headers["Authorization"])) {
        $token = str_replace("Bearer ", "", $headers["Authorization"]);

        $userId = mysqli_fetch_array($connect->query("SELECT Id FROM `user` WHERE `user`.`Token` = '$token'"))[0];

        if (isset($userId)) {
            UploadImage($userId);
        } else {
            http_response_code(401);
            exit();
        }
    } else {
        http_response_code(401);
        exit();
    }

}

?>