<?php
    require_once('settings.php');

    if (AWAITING_PATCH_DATA == 1) {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $DBPatch = 0;
        $stmt = $mysqli->prepare("SELECT `PatchID` FROM `PatchList` ORDER BY `PatchID` DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_row()) {
            $DBPatch = $row[0];
        }
        $stmt->close();
        $PatchList = file_get_contents('https://xivapi.com/patchlist?private_key='.XIVAPI_KEY);
        $CacheFile = SRC_DIR.'/Cache/XIVAPI_Mounts.crc32.txt';
        $Hash = hash('crc32', $PatchList);
        $OldHash = file_get_contents($CacheFile);
    
        if ($Hash != $OldHash) {
            $LockUpdate = 0;
            $stmt = $mysqli->prepare("UPDATE `PatchDue` SET `PatchDue` = ?");
            $stmt->bind_param('i', $LockUpdate);
            $stmt->execute();
            $stmt->close();
            $JSON = json_decode($PatchList);
            foreach ($JSON as $Patch) {
                if ($Patch->Version > $DBPatch) {
                    $stmt = $mysqli->prepare("INSERT INTO `PatchList` (`PatchID`) VALUES (?)");
                    $stmt->bind_param('d', $Patch->Version);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            //XIVAPI get mounts
            $RemoteFile = 'https://xivapi.com/search?indexes=Mount&columns=ID,IconSmall,Name_en,'
                        .'GamePatch.Version&filters=Order>=0,GamePatch.Version>='.floor($DBPatch)
                        .'&limit=1000&private_key='.XIVAPI_KEY;
            $Mounts = file_get_contents($RemoteFile);
            $JSON = json_decode($Mounts);
            foreach ($JSON->Results as $Mount) {
                if (($Mount->GamePatch->Version > $DBPatch) AND (!empty($Mount->IconSmall))) {
                    echo('Patch '.$Mount->GamePatch->Version.': Inserted '.$Mount->Name_en." into Database,\n");
                    $stmt = $mysqli->prepare("INSERT INTO `XIVAPICacheMounts` (`ID`, `IconSmall`, `Name`) VALUES (?, ?, ?)");
                    $stmt->bind_param('iss', $Mount->ID, $Mount->IconSmall, $Mount->Name_en);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            file_put_contents($CacheFile);
        } else {
            echo('XIVAPI has not yet been updated. Nothing to do.');
        }
    } else {
        echo('No patch data due.');
    }
    echo("\n");