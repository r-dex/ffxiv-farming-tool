<?php
    set_time_limit('3600');
    ini_set('output_buffering', 'off');
    require_once('../Private/settings.php');
    if (MAINTENANCE == 1) {
        die('Under Maintenance.');
    }
    require_once(SRC_DIR.'/functions.php');

    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    spl_autoload_register(function($Class) {
        require(SRC_DIR.'/'.str_replace('\\', '/', $Class).'.php');
    });

    $MountList = [];
    $stmt = $mysqli->prepare("SELECT `ID`, `Name` FROM `XIVAPICacheMounts` WHERE LENGTH(`IconSmall`) > 0");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_object()) {
        $MountName = strtolower($row->Name);
        $MountList[$MountName] = $row->ID;
    }
    $stmt->close();

    $i = 0;
    $CustomGroupID = randomString();
    $stmt = $mysqli->prepare("SELECT `CustomGroupID` FROM `CustomGroups` WHERE `CustomGroupID` = ?");
    $stmt->bind_param('s', $CustomGroupID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        while ($i < 1) {
            $CustomGroupID = randomString();
            $stmt2 = $mysqli->prepare("SELECT `CustomGroupID` FROM `CustomGroups` WHERE `CustomGroupID` = ?");
            $stmt2->bind_param('s', $CustomGroupID);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            if ($result2->num_rows == 0) {
                $i++;
            }
            $stmt2->close();
        }
    }
    $stmt->close();

    $CustomGroupName = 'Custom Group';
    $CustomGroupTime = time();
    $stmt = $mysqli->prepare("INSERT INTO `CustomGroups` (`CustomGroupID`, `CustomGroupName`, `CustomGroupLastAccessed`, `CustomGroupLastUpdated`) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssii', $CustomGroupID, $CustomGroupName, $CustomGroupTime, $CustomGroupTime);
    $stmt->execute();
    $stmt->close();
    echo('New Custom Group Created.<br />');
    flush();

    foreach ($_GET as $Key => $Value) {
        if (substr($Key, 0, -1) == 'CharacterID') {
            $CharacterID = (int)$Value;
            $stmt = $mysqli->prepare("INSERT INTO `CustomGroupMembers` (`CustomGroupID`, `CharacterID`) VALUES (?, ?)");
            $stmt->bind_param('si', $CustomGroupID, $Value);
            $stmt->execute();
            $stmt->close();
            echo('Added New Member to Custom Group.<br />');
            $Character = new Rosaworks\Lodestone\Character();
            if ($Character->getCharacter($CharacterID)) {
                $CharacterName = $mysqli->real_escape_string($Character->Name);
                if (empty($Character->FreeCompany['ID'])) {
                    $FreeCompanyID = '-';
                } else {
                    $FreeCompanyID = $Character->FreeCompany['ID'];
                }
                $sql = "INSERT INTO `Characters` (`CharacterID`, `CharacterName`, `FreeCompanyID`, `LastUpdated`)"
                    ." VALUES ('".$Character->ID."', '".$CharacterName."', '".$FreeCompanyID."', '".time()."')"
                    ." ON DUPLICATE KEY UPDATE"
                    ." `CharacterID` = '".$Character->ID."',"
                    ." `CharacterName` = '".$CharacterName."',"
                    ." `FreeCompanyID` = '".$FreeCompanyID."',"
                    ." `LastUpdated` = '".time()."';\n";
                echo('&raquo; Adding Character ('.$Character->ID.': '.$Character->Name.'),<br />');
                $mysqli->query($sql);
                $Character->getMounts();
                $sql = "INSERT IGNORE INTO `Mounts` (`CharacterID`, `MountID`) VALUES ";
                $MountString = '&raquo; &raquo; Adding Mounts (';
                $i = 0;
                foreach ($Character->Mounts as $Mount) {
                    $Mount = ucwords($Mount);
                    $sql .= "('".$Value."', '".$MountList[strtolower($Mount)]."'), ";
                    $MountString .= $Mount.', ';
                    $i++;
                }
                if ($i > 0) {
                    $sql = substr($sql, 0, -2);
                    $sql .= ";\n";
                    $MountString = substr($MountString, 0, -2);
                    echo($MountString.'),<br />');
                    $mysqli->query($sql);
                }
                flush();
            }
        }
    }
        
    echo('<script>window.location.replace("'.BASE_URL.'/'.$CustomGroupID.'");</script>');