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
    switch (sizeof($urlData)) {
        case 0:
            PrintCities();
            break;
        case 1:
            if (is_numeric($urlData[0])) {
                PrintCity($urlData[0]);
            } else {
                http_response_code(403);
            }
            break;
        case 2:
            if ($urlData[1] == "peoples" and is_numeric($urlData[0])) {
                PrintPeopleFromCity($urlData[0]);
            } else {
                http_response_code(403);
            }

            break;
        default:
            http_response_code(501);
            break;
    }
}

function Post($urlData, $formData)
{
    global $connect;

    $headers = getallheaders();
    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if (Count($urlData) === 0) {
        if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {

            $name = $formData->Name;
            $connect->query("INSERT INTO `city` (`Id`, `Name`) VALUES (NULL, '$name')");

        }
    }
}

function Patch($urlData, $formData)
{
    global $connect;

    $headers = getallheaders();
    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if (Count($urlData) === 1) {
        if (is_numeric($urlData[0])) {
            if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {
                $cityId = (int)$urlData[0];

                if(isset($formData->Name) && $formData->Name != ""){
                    $connect->query("UPDATE `city` SET `Name` = '$formData->Name' WHERE `city`.`Id` = '$cityId'");
                }
                else{
                    http_response_code(400);
                }
            }
            else{
                http_response_code(403);
            }
        }
    }

}

function Delete($urlData)
{
    global $connect;

    $headers = getallheaders();
    if (isset($headers["Authorization"])) $generalAccessLevel = GetAccessLevel($headers["Authorization"]);
    else $generalAccessLevel = UNAUTHORIZED_ACCESS_LEVEL;

    if (Count($urlData) === 1) {
        if (is_numeric($urlData[0])) {
            if ($generalAccessLevel == ADMIN_ACCESS_LEVEL) {
                $cityId = $urlData[0];
                $connect->query("DELETE FROM `city` WHERE `city`.`Id` = '$cityId'");
            }
            else{
                http_response_code(403);
            }
        }
    }
}

function PrintCities()
{
    global $connect;
    $cities = $connect->query("SELECT * FROM city");
    PrintJson($cities);
    exit();
}

function PrintCity($cityId)
{
    global $connect;
    $cities = $connect->query("SELECT * FROM `city` WHERE `id` = $cityId");
    PrintJson($cities);
    exit();
}

function PrintPeopleFromCity($cityId)
{
    global $connect;
    $request = "SELECT `user`.`Id`, `user`.`Name`, Surname, `user`.Username, `user`.Avatar,`user`.Status, c.`Name` as City
             FROM `user`
                 JOIN `city` AS c 
                    ON c.`Id`=`user`.`CityId`
                         WHERE `user`.CityId = $cityId";

    PrintJson($connect->query($request));
    exit();
}

?>