<?php
    function buildMountString($Member, $FarmedMounts, $TotalFarmedMounts, $MountInfo)
    {
        $MountString = '';
        $MountsFarmed = 0;
        $MissingMounts = '[';
        foreach ($FarmedMounts as $Mount) {
            $String = '<div class="box"><div class="icon"><img alt="'.$Mount.'" class="mount-icon" src="'.$MountInfo[$Mount]['Icon'].'" /></div>'
                    .'<div class="tooltip-overlay" data-html="true" data-toggle="tooltip" data-placement="bottom" title="<em>'.$Mount.'</em><br />'
                    .$MountInfo[$Mount]['Duty'].'"></div></div>';
            if (in_array($Mount, $Member->Mounts)) {
                $MountsFarmed++;
            } else {
                $MissingMounts .= "'".$MountInfo[$Mount]['Duty']."', ";
                $String = str_replace('</div></div>', '</div><div class="shade"></div></div>', $String);
            }
            $MountString .= $String;
        }
        $MissingMounts = substr($MissingMounts, 0, -2).']';
        $MountString = '<div><div class="member-button"><button class="btn btn-primary" id="btn'.$Member->ID
                    .'" onclick="btnClick('.$Member->ID.', '.$MissingMounts.')" type="button">+</button></div><div class="member-name">'.$Member->Name
                    .' ('.$MountsFarmed.'/'.$TotalFarmedMounts.'):</div><div style="display:inline-block;">'
                    .$MountString.'</div></div>';
        return $MountString;
    }

    function getFarmedMountsList($mysqli)
    {
        $Response = null;
        $sql = "SELECT `Name` FROM `XIVAPICacheMounts` WHERE "
            ."`Name` LIKE '%Lanner' "
            ."OR `Name` LIKE '%Kamuy' "
            ."OR `Name` = 'Rathalos' "
            ."OR `Name` LIKE '%Gwiber' "
            ."ORDER BY `ID` ASC";
        $result = $mysqli->query($sql);
        $FarmedMounts = ['Nightmare', 'Xanthos', 'Gullfaxi', 'Aithon', 'Enbarr', 'Markab', 'Boreas'];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_row()) {
                $FarmedMounts[] = ucwords($row[0]);
            }
        }
        $result->free();
        $TotalFarmedMounts = count($FarmedMounts);
        if ($TotalFarmedMounts > 0) {
            $Response = [$TotalFarmedMounts, $FarmedMounts];
        }
        return $Response;
    }

    function getDutyList($FarmedMounts, $mysqli)
    {
        $DutyList = '[';
        $MountInfo = [];
        foreach ($FarmedMounts as $Mount) {
            $stmt = $mysqli->prepare("SELECT `Duty`, `IconSmall` FROM `XIVAPICacheMounts` WHERE LOWER(`Name`) = LOWER(?)");
            $stmt->bind_param('s', $Mount);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                while ($row = $result->fetch_row()) {
                    $DutyList .= "'".$row[0]."', ";
                    $MountInfo[$Mount] = [
                        'Duty' => $row[0],
                        'Icon' => CDN_URL.$row[1]
                    ];
                }
            }
            $stmt->close();
        }
        $DutyList = substr($DutyList, 0, -2).']';
        return [$DutyList, $MountInfo];
    }

    function randomString($length = 6)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }