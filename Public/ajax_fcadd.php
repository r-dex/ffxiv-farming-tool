<?php
    set_time_limit('3600');
    ini_set('output_buffering', 'off');
    require_once('../Private/settings.php');
    if (MAINTENANCE == 1) {
        die('Under Maintenance.');
    }
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

    if (ctype_digit($_GET['fc'])) {
        $FreeCompanyID = $_GET['fc'];

        $stmt = $mysqli->prepare("SELECT `FreeCompanyID` FROM `FreeCompanies` WHERE `FreeCompanyID` = ?");
        $stmt->bind_param('s', $FreeCompanyID);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows == 1) {
            die('This Free Company already exists. Data will be updated daily.');
        } else {
            $FreeCompany = new Rosaworks\Lodestone\FreeCompany();
            if ($FreeCompany->getFreeCompany($FreeCompanyID)) {
                $FCName = $mysqli->real_escape_string($FreeCompany->Name);
                $FCTag = $mysqli->real_escape_string($FreeCompany->Tag);
                $FCServer = $mysqli->real_escape_string($FreeCompany->Server);
                $FCCrest = $mysqli->real_escape_string($FreeCompany->Crest->Crest);
                $sql = "INSERT INTO `FreeCompanies` (`FreeCompanyID`, `FreeCompanyName`, `FreeCompanyTag`, `FreeCompanyServer`, `FreeCompanyCrest`, `FreeCompanyLastUpdate`)"
                    ." VALUES ('".$FreeCompany->ID."', '".$FCName."', '".$FCTag."', '".$FCServer."', '".$FCCrest."', '".time()."');\n";
                echo('Adding FreeCompany ('.$FreeCompanyID.': '.$FCName.'),<br />');
                $mysqli->query($sql);
                ob_flush();
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
                        $sql = "INSERT INTO `Characters` (`CharacterID`, `CharacterName`, `FreeCompanyID`, `LastUpdated`)"
                            ." VALUES ('".$Member->ID."', '".$MemberName."', '".$FreeCompany->ID."', '".time()."')"
                            ." ON DUPLICATE KEY UPDATE"
                            ." `CharacterID` = '".$Member->ID."',"
                            ." `CharacterName` = '".$MemberName."',"
                            ." `FreeCompanyID` = '".$FreeCompany->ID."',"
                            ." `LastUpdated` = '".time()."';\n";
                        echo('&raquo; Adding Character ('.$Member->ID.': '.$Member->Name.'),<br />');
                        $mysqli->query($sql);
                        $Character->getMounts();
                        $sql = "INSERT IGNORE INTO `Mounts` (`CharacterID`, `MountID`) VALUES ";
                        $MountString = '&raquo; &raquo; Adding Mounts (';
                        $i = 0;
                        foreach ($Character->Mounts as $Mount) {
                            $Mount = ucwords($Mount);
                            $sql .= "('".$Member->ID."', '".$MountList[strtolower($Mount)]."'), ";
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
                        ob_flush();
                        flush();
                    }
                }
        
                echo('<script>window.location.reload(true);</script>');
            }
        }
        $stmt->close();
    } else {
        die('Invalid Data Specified for Free Company ID.');
    }