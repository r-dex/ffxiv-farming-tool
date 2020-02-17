<?php
    require_once('../Private/settings.php');
    if (MAINTENANCE == 1) {
        die('Under Maintenance.');
    }
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    $CharacterList = [];
    if (!empty($_GET['char-1-name'])) {
        $CharacterList[] = [$_GET['char-1-server'], str_replace('%20', '+', $_GET['char-1-name'])];
    }
    if (!empty($_GET['char-2-name'])) {
        $CharacterList[] = [$_GET['char-2-server'], str_replace('%20', '+', $_GET['char-2-name'])];
    }
    if (!empty($_GET['char-3-name'])) {
        $CharacterList[] = [$_GET['char-3-server'], str_replace('%20', '+', $_GET['char-3-name'])];
    }
    if (!empty($_GET['char-4-name'])) {
        $CharacterList[] = [$_GET['char-4-server'], str_replace('%20', '+', $_GET['char-4-name'])];
    }
    if (!empty($_GET['char-5-name'])) {
        $CharacterList[] = [$_GET['char-5-server'], str_replace('%20', '+', $_GET['char-5-name'])];
    }
    if (!empty($_GET['char-6-name'])) {
        $CharacterList[] = [$_GET['char-6-server'], str_replace('%20', '+', $_GET['char-6-name'])];
    }
    if (!empty($_GET['char-7-name'])) {
        $CharacterList[] = [$_GET['char-7-server'], str_replace('%20', '+', $_GET['char-7-name'])];
    }
    if (!empty($_GET['char-8-name'])) {
        $CharacterList[] = [$_GET['char-8-server'], str_replace('%20', '+', $_GET['char-8-name'])];
    }

    if (count($CharacterList) == 0) {
        $ServerList = '';
        $stmt = $mysqli->prepare("SELECT `Server` FROM `Servers` ORDER BY `Server` ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_row()) {
            $ServerList .= '<option value="'.$row[0].'">'.$row[0].'</option>';
        }
        $stmt->close();
        echo('<div class="alert alert-danger" role="alert">At least one Character must be specified.</div>');
        $Replace = [];
        $Replace['%server-list%'] = $ServerList;
        echo(str_replace(array_keys($Replace), $Replace, file_get_contents(TEMPLATE_DIR.'/customgroupform.html')));
    } else {
        $OKCharacters = [];
        $FailedCharacters = [];
        foreach ($CharacterList as $Character) {
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, 'https://eu.finalfantasyxiv.com/lodestone/character/?q="'.$Character[1].'"&worldname='.$Character[0]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $ch_output = curl_exec($ch);
            $ch_info = curl_getinfo($ch);
            curl_close($ch);

            if ($ch_info['http_code'] == 200) {
                $DOM = new \DOMDocument;
                @$DOM->loadHTML('<?xml encoding="UTF-8">'.$ch_output);
                $DOMElement =  $DOM->getElementsByTagName('a');
                $i = 0;
                foreach ($DOMElement as $Element) {
                    foreach ($Element->attributes as $Attributes) {
                        if ($Attributes->nodeValue == 'entry__link') {
                            $Data = $DOM->saveHTML($Element);
                            $Data = explode('<', $Data);
                            
                            $ID = trim(str_replace(array('a href="/lodestone/character/', '/" class="entry__link">'), '', $Data[1]));
                            $Name = trim(str_replace('p class="entry__name">', '', $Data[6]));
                            $Server = explode('(', trim(str_replace(array('/i>'), '', $Data[10])));
                            $Server = preg_replace( '/[^a-zA-Z]/i', '', $Server[0]);
                            $Avatar = explode('"', trim(str_replace('img src="', '', $Data[3])));
                            $Avatar = trim($Avatar[0]);
                            $OKCharacters[] = [
                                'ID' => $ID,
                                'Name' => $Name,
                                'Server' => $Server,
                                'Avatar' => $Avatar
                            ];
                            echo('<!-- Output Generation -->');
                            flush();
                        }
                    }
                }
            } else {
                $FailedCharacters[] = [
                    'Name' => $Character[1],
                    'Server' => $Character[0]
                ];
                echo('<!-- Output Generation -->');
                flush();
            }
        }

        $i = 1;
        if (count($FailedCharacters) == 0) {
            if (!empty($_GET['GroupName'])) {
                $GroupName = $mysqli->real_escape_string(urldecode($_GET['GroupName']));
            } else {
                $GroupName = 'Custom Group';
            }
            echo('<form id="CustomGroupConfirmation">');
            echo('<h3>'.$GroupName.'</h3><input type="hidden" id="GroupName" name="GroupName" value="'.$GroupName.'" />');
            foreach ($OKCharacters as $Character) {
                echo('<div');
                if ($i > 1) {
                    echo(' style="border-top:1px solid white; padding-top: 5px;"');
                }
                echo('><input type="hidden" id="CharacterID'.$i.'" name="CharacterID'.$i.'" value="'.$Character['ID'].'" />');
                echo('<div style="display:inline-block; margin-bottom:10px; margin-right:10px; width:40px;">');
                echo('<img alt="'.$Character['Name'].'" src="'.$Character['Avatar'].'" style="border-radius:20px; height:40px; width:40px;" /></div>');
                echo('<div style="display:inline-block;">'.$Character['Name'].' ('.$Character['Server'].')</div></div>');
                $i++;
            }
            echo('</form><button type="button" class="btn btn-success" onclick="CustomGroupAdd()">Save Group</button>');
        } else {
            $ServerList = '';
            $stmt = $mysqli->prepare("SELECT `Server` FROM `Servers` ORDER BY `Server` ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_row()) {
                $ServerList .= '<option value="'.$row[0].'">'.$row[0].'</option>';
            }
            $stmt->close();
            echo('<div class="alert alert-danger" role="alert">One or more characters were not found. Please try again.</div>');
            $Replace = [];
            $Replace['%server-list%'] = $ServerList;
            echo(str_replace(array_keys($Replace), $Replace, file_get_contents(TEMPLATE_DIR.'/customgroupform.html')));
        }
    }