<?php
    require_once('../Private/settings.php');
    if (MAINTENANCE == 1) {
        echo(file_get_contents(TEMPLATE_DIR.'/maintenance.html'));
        die();
    }
    require_once(SRC_DIR.'/functions.php');
    
    spl_autoload_register(function($Class) {
        require(SRC_DIR.'/'.str_replace('\\', '/', $Class).'.php');
    });

    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if (isset($_GET['customgroup'])) {
        //Custom Group
        $sql = "SELECT `CustomGroupID` AS `ID`, `CustomGroupName` AS `Name`, `CustomGroupLastAccessed` AS `LastAccessed`, `CustomGroupLastUpdated` AS `LastUpdated`"
                ." FROM `CustomGroups` WHERE `CustomGroupID` = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $_GET['customgroup']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            //Custom Group Found
            while ($Object = $result->fetch_object()) {
                $CharacterGroup = new Rosaworks\FarmingTool\CustomGroup($Object);
                $sqlCharacters = "SELECT `CustomGroupMembers`.`CharacterID` AS `ID`, `Characters`.`CharacterName` AS `Name`"
                                ." FROM `CustomGroupMembers` INNER JOIN `Characters` ON `CustomGroupMembers`.`CharacterID` = `Characters`.`CharacterID`"
                                ." WHERE `CustomGroupMembers`.`CustomGroupID` = ? ORDER BY `Name` ASC";
                $stmtCharacters = $mysqli->prepare($sqlCharacters);
                $stmtCharacters->bind_param('s', $Object->ID);
                $stmtCharacters->execute();
                $resultCharacters = $stmtCharacters->get_result();
                if ($resultCharacters->num_rows > 0) {
                    $MemberList = [];
                    $Mounts = [];
                    while ($Member = $resultCharacters->fetch_object()) {
                        $MemberList[] = $Member;
                        $stmtMounts = $mysqli->prepare("SELECT `Mounts`.`MountID`, `XIVAPICacheMounts`.`Name` FROM `Mounts`"
                        ." INNER JOIN `XIVAPICacheMounts` ON `Mounts`.`MountID` = `XIVAPICacheMounts`.`ID`"
                        ." WHERE `CharacterID` = ?");
                        $stmtMounts->bind_param('i', $Member->ID);
                        $stmtMounts->execute();
                        $resultMounts = $stmtMounts->get_result();
                        $CharacterMounts = [];
                        while ($Mount = $resultMounts->fetch_object()) {
                            $CharacterMounts[] = ucwords($Mount->Name);
                        }
                        $Mounts[] = $CharacterMounts;
                        $stmtMounts->close();
                    }
                    $CharacterGroup->setMembers($MemberList, $Mounts);
                }
                $stmtCharacters->close();
            }
            $stmtUpdate = $mysqli->prepare("UPDATE `CustomGroups` SET `CustomGroupLastAccessed` = ?");
            $Time = time();
            $stmtUpdate->bind_param('s', $Time);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } else {
            //Error page, not found
        }
        $stmt->close();
    }
    if (isset($_GET['freecompany']) && !empty($_GET['freecompany'])) {
        //Free Company
        $CharacterGroup = '';
        $sql = "SELECT `FreeCompanyID` AS `ID`, `FreeCompanyName` AS `Name`, `FreeCompanyTag` AS `Tag`,"
            ."`FreeCompanyServer` AS `Server`, `FreeCompanyCrest` AS `Crest`, `FreeCompanyLastUpdate` AS `LastUpdated`"
            ." FROM `FreeCompanies` WHERE `FreeCompanyID` = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $_GET['freecompany']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            //FreeCompany Page
            while ($Object = $result->fetch_object()) {
                $CharacterGroup =  new Rosaworks\FarmingTool\FreeCompany($Object);
                $stmtCharacters = $mysqli->prepare("SELECT `CharacterID` AS `ID`, `CharacterName` AS `Name`"
                    ." FROM `Characters` WHERE `FreeCompanyID` = ? ORDER BY `Name` ASC");
                $stmtCharacters->bind_param('s', $Object->ID);
                $stmtCharacters->execute();
                $resultCharacters = $stmtCharacters->get_result();
                if ($resultCharacters->num_rows > 0) {
                    $MemberList = [];
                    $Mounts = [];
                    while ($Member = $resultCharacters->fetch_object()) {
                        $MemberList[] = $Member;
                        $stmtMounts = $mysqli->prepare("SELECT `Mounts`.`MountID`, `XIVAPICacheMounts`.`Name` FROM `Mounts`"
                            ." INNER JOIN `XIVAPICacheMounts` ON `Mounts`.`MountID` = `XIVAPICacheMounts`.`ID`"
                            ." WHERE `CharacterID` = ?");
                        $stmtMounts->bind_param('i', $Member->ID);
                        $stmtMounts->execute();
                        $resultMounts = $stmtMounts->get_result();
                        $CharacterMounts = [];
                        while ($Mount = $resultMounts->fetch_object()) {
                            $CharacterMounts[] = ucwords($Mount->Name);
                        }
                        $Mounts[] = $CharacterMounts;
                        $stmtMounts->close();
                    }
                    $CharacterGroup->setMembers($MemberList, $Mounts);
                }
                $stmtCharacters->close();
            }
        } else {
            //XIVAPI Process

        }
        $stmt->close();
    }
    if (empty($CharacterGroup)) {
        if (!empty($_GET['freecompany'])) {
            $HTML = file_get_contents(TEMPLATE_DIR.'/addfreecompany.html');
            $Replace = [];
            $URL = 'https://xivapi.com/freecompany/'.$_GET['freecompany']
                .'?columns=FreeCompany.ID,FreeCompany.Name,FreeCompany.Crest,FreeCompany.Server&private_key='
                .XIVAPI_KEY;
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $JSON = curl_exec($ch);
            curl_close($ch);
            $JSON = json_decode($JSON);
            $Replace['%fc-id%'] = $JSON->FreeCompany->ID;
            $Replace['%fc-name%'] = $JSON->FreeCompany->Name;
            $Replace['%fc-server%'] = $JSON->FreeCompany->Server;
            $Replace['%fc-crest%'] = $JSON->FreeCompany->Crest[2];
            echo(str_replace(array_keys($Replace), $Replace, $HTML));
        } else {
            $Replace = [];
            $ServerList = '';
            $stmt = $mysqli->prepare("SELECT `Server` FROM `Servers` ORDER BY `Server` ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_row()) {
                $ServerList .= '<option value="'.$row[0].'">'.$row[0].'</option>';
            }
            $stmt->close();
            $Replace['%custom-group-form%'] = file_get_contents(TEMPLATE_DIR.'/customgroupform.html');
            $Replace['%server-list%'] = $ServerList;
            echo(str_replace(array_keys($Replace), $Replace, file_get_contents(TEMPLATE_DIR.'/homepage.html')));
        }
    } else {
        $FarmedMountsArray = getFarmedMountsList($mysqli);
        $TotalFarmedMounts = $FarmedMountsArray[0];
        $FarmedMounts = $FarmedMountsArray[1];
        $DutyInformation = getDutyList($FarmedMounts, $mysqli);
        $DutyList = $DutyInformation[0];
        $MountInfo = $DutyInformation[1];
        $HTML = file_get_contents(TEMPLATE_DIR.'/charactergroup.html');
        $Replace = [];
        $Tag = '';
        $TagString = '';
        if (!empty($CharacterGroup->Tag)) {
            $Tag = $CharacterGroup->Tag;
            $TagString = '&lt;'.$Tag.'&gt';
        }
        $FCString = '<div class="bg-dark headrow"><img alt="'.$Tag.'" id="FCIcon" src="'.$CharacterGroup->Crest.'" />'
                    .'<div id="FCName">'.$CharacterGroup->Name.' '.$TagString.'</div>'
                    .'<div class="dutybutton"><button type="button" class="btn btn-success" data-toggle="modal" data-target="#DutyModal">View Duties</button></div>'
                    .'<br /><span style="font-size:small">Last Updated: '.gmdate('j-M-y H:i', $CharacterGroup->LastUpdated).' ST</span></div>';
        $Replace['%fc-header%'] = $FCString;
        $Replace['%fc-id%'] = $CharacterGroup->ID;
        $Replace['%fc-og-string%'] = ' for '.$CharacterGroup->Name.' '.$TagString.' ('.$CharacterGroup->Server.')';
        $Replace['%duty-list-array%'] = $DutyList;
        $MountString = '';
        foreach ($CharacterGroup->Members as $Member) {
            $MountString .= buildMountString($Member, $FarmedMounts, $TotalFarmedMounts, $MountInfo);
        }
        $Replace['%mount-list%'] = $MountString;
        echo(str_replace(array_keys($Replace), $Replace, $HTML));
    }