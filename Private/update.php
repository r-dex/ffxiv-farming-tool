<?php
    set_time_limit('3600');
    ini_set('output_buffering', 'off');
    require_once('settings.php');
    if (MAINTENANCE == 1) {
        die('Under Maintenance.');
    }

    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    spl_autoload_register(function($Class) {
        require(SRC_DIR.'/'.str_replace('\\', '/', $Class).'.php');
    });

    $UpdateList = [];
    $TwentyFourHoursAgo = time() - 86339;
    $stmt = $mysqli->prepare("SELECT `FreeCompanyID` FROM  `FreeCompanies` WHERE `FreeCompanyLastUpdate` < ?");
    $stmt->bind_param('i', $TwentyFourHoursAgo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_row()) {
            $UpdateList[] = ['ID' => $row[0], 'Type' => 'FreeCompany'];
        }
    }
    $stmt->close();
    $stmt = $mysqli->prepare("SELECT `CustomGroupID` FROM  `CustomGroups` WHERE `CustomGroupLastUpdated` < ?");
    $stmt->bind_param('i', $TwentyFourHoursAgo);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_row()) {
            $UpdateList[] = ['ID' => $row[0], 'Type' => 'CustomGroup'];
        }
    }
    $stmt->close();

    if (count($UpdateList) > 0) {
        $MountList = [];
        $stmt = $mysqli->prepare("SELECT `ID`, `Name` FROM `XIVAPICacheMounts` WHERE LENGTH(`IconSmall`) > 0");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_object()) {
            $MountName = strtolower($row->Name);
            $MountList[$MountName] = $row->ID;
        }
        $stmt->close();

        echo(count($UpdateList)." groups need updating.\n");
        foreach ($UpdateList as $Group) {
            if ($Group['Type'] == 'FreeCompany') {
                //FreeCompany make string of SQL insert commands commencing with DELETE FROM `Characters` WHERE `FreeCompanyID` = ? then multi-line execute
                $sql = "DELETE FROM `Characters` WHERE `FreeCompanyID` = '{$Group['ID']}';\n";
                echo($sql);
                flush();

                $FreeCompany = new Rosaworks\Lodestone\FreeCompany();
                if ($FreeCompany->getFreeCompany($Group['ID'])) {
                    $FCName = $mysqli->real_escape_string($FreeCompany->Name);
                    $FCTag = $mysqli->real_escape_string($FreeCompany->Tag);
                    $FCServer = $mysqli->real_escape_string($FreeCompany->Server);
                    $FCCrest = $mysqli->real_escape_string($FreeCompany->Crest->Crest);
                    $line = "UPDATE `FreeCompanies` SET `FreeCompanyName` = '".$FCName."', `FreeCompanyTag` = '".$FCTag
                        ."', `FreeCompanyServer` = '".$FCServer."', `FreeCompanyCrest` = '".$FCCrest."', `FreeCompanyLastUpdate` = '".time()
                        ."' WHERE `FreeCompanyID` = '".$FreeCompany->ID."';\n";
                    echo($line);
                    $sql .= $line;
                    flush();
                
                    $Count = floor($FreeCompany->MemberCount / 50);
                    $Count = $Count + 1;
                    $i = 1;
                    $FreeCompanyMembers = [];
                    while ($i <= $Count) {
                        $FreeCompany->getMembers($i);
                        $i++;
                    }
            
                    foreach ($FreeCompany->Members as $Member) {
                        $Character = new Rosaworks\Lodestone\Character();
                        if ($Character->setID($Member->ID)) {
                            $MemberName = $mysqli->real_escape_string($Member->Name);
                            $line = "INSERT INTO `Characters` (`CharacterID`, `CharacterName`, `FreeCompanyID`, `LastUpdated`)"
                                ." VALUES ('".$Member->ID."', '".$MemberName."', '".$FreeCompany->ID."', '".time()."')"
                                ." ON DUPLICATE KEY UPDATE"
                                ." `CharacterID` = '".$Member->ID."',"
                                ." `CharacterName` = '".$MemberName."',"
                                ." `FreeCompanyID` = '".$FreeCompany->ID."',"
                                ." `LastUpdated` = '".time()."';\n";
                            echo($line);
                            $sql .= $line;
                            flush();

                            $Character->getMounts();
                            $line = "INSERT IGNORE INTO `Mounts` (`CharacterID`, `MountID`) VALUES ";
                            $i = 0;
                            foreach ($Character->Mounts as $Mount) {
                                $Mount = ucwords($Mount);
                                $line .= "('".$Member->ID."', '".$MountList[strtolower($Mount)]."'), ";
                                $i++;
                            }
                            if ($i > 0) {
                                $line = substr($line, 0, -2);
                                $line .= ";\n";
                                echo($line);
                                $sql .= $line;
                                flush();
                            }
                        }
                    }

                    $mysqli->multi_query($sql);
                }
            } elseif ($Group['Type'] == 'CustomGroup') {
                $TimeNow = time();
                $stmt = $mysqli->prepare("UPDATE `CustomGroups` SET `CustomGroupLastUpdated` = ? WHERE `CustomGroupID` = ?");
                $stmt->bind_param('ss', $TimeNow, $Group['ID']);
                $stmt->execute();
                $stmt->close();
                $stmt = $mysqli->prepare("SELECT `CustomGroupMembers`.`CharacterID`"
                    ." FROM `CustomGroupMembers` INNER JOIN `Characters` ON `CustomGroupMembers`.`CharacterID` = `Characters`.`CharacterID`"
                    ." WHERE `CustomGroupMembers`.`CustomGroupID` = ? AND `Characters`.`LastUpdated` < ?");
                $stmt->bind_param('ss', $Group['ID'], $TwentyFourHoursAgo);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_row()) {
                        $Character = new Rosaworks\Lodestone\Character();
                        if ($Character->getCharacter($row[0])) {
                            $CharacterName = $mysqli->real_escape_string($Character->Name);
                            $sql = "INSERT INTO `Characters` (`CharacterID`, `CharacterName`, `FreeCompanyID`, `LastUpdated`)"
                                ." VALUES ('".$Character->ID."', '".$CharacterName."', '".$Character->FreeCompany['ID']."', '".time()."')"
                                ." ON DUPLICATE KEY UPDATE"
                                ." `CharacterID` = '".$Character->ID."',"
                                ." `CharacterName` = '".$CharacterName."',"
                                ." `FreeCompanyID` = '".$Character->FreeCompany['ID']."',"
                                ." `LastUpdated` = '".time()."';\n";
                            echo($sql);
                            $mysqli->query($sql);
                            $Character->getMounts();
                            $sql = "INSERT IGNORE INTO `Mounts` (`CharacterID`, `MountID`) VALUES ";
                            $MountString = '&raquo; &raquo; Adding Mounts (';
                            $i = 0;
                            foreach ($Character->Mounts as $Mount) {
                                $Mount = ucwords($Mount);
                                $sql .= "('".$Character->ID."', '".$MountList[strtolower($Mount)]."'), ";
                                $MountString .= $Mount.', ';
                                $i++;
                            }
                            if ($i > 0) {
                                $sql = substr($sql, 0, -2);
                                $sql .= ";\n";
                                $MountString = substr($MountString, 0, -2);
                                echo($MountString."),\n");
                                $mysqli->query($sql);
                            }
                        }
                    }
                } else {
                    echo("Characters for this Custom Group have been updated recently. Nothing to do.\n");
                }
                $stmt->close();
            }
        }
    } else {
        echo("Nothing to do.\n");
    }