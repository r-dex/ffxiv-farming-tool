<?php
    require_once('../Private/settings.php');
    if (MAINTENANCE == 1) {
        die('Under Maintenance.');
    }
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if (empty($_GET['fc'])) { die('No Free Company Specified'); }

    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, 'https://xivapi.com/freecompany/search?name='.urlencode($_GET['fc']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $html = curl_exec($ch);
    curl_close($ch);

    $JSON = json_decode($html);
    if ($JSON->Pagination->Results > 0) {
        //One or more FC Found, make some handlers.
        $Output = '';
        $i = 1;
        foreach ($JSON->Results AS $Result) {
            $Button = '<a class="btn btn-warning" href="/'.$Result->ID.'" role="button" style="width:120px;">Add This FC<sup>1</sup></a>';
            $stmt = $mysqli->prepare("SELECT `FreeCompanyID` FROM `FreeCompanies` WHERE `FreeCompanyID` = ?");
            $stmt->bind_param('s', $Result->ID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $Button = '<a class="btn btn-success" href="/'.$Result->ID.'" role="button" style="width:120px;">Select<sup>2</sup></a>';
            }
            $stmt->close();
            if ($i > 1) {
                $Output .= '<div style="border-top:1px solid white;width:460px;">';
            } else {
                $Output .= '<div style="width:460px;">';
            }
            $Output .='<div style="display:inline-block;width:64px;text-align:center;"><img alt="FC" src="'.$Result->Crest[2].'" /></div>'
                    .'<div style="display:inline-block;width:250px;">'.$Result->Name.' ('.$Result->Server.')</div>'
                    .'<div style="display:inline;padding-left:10px;">'.$Button.'</div>'
                    .'</div>';
            $i++;
        }
        echo($Output.'<br /><sup>1: Please note this will require us to scrape Lodestone first and may take several minutes.<br />2: This Free Company is already available to this service and is updated daily.</sup>');
    } else {
        //None found, error!
        echo('<span class="text-danger">Specified Free Company could not be Found. Please review your entry and try again.</span>');
        $Replace = [
            '<input type' => '<input value="'.$_GET['fc'].'" type',
            '<h2>Select your Free Company</h2>' => '',
            'id="FCSelector"' => 'style="margin-top:15px;"'
        ];
        echo(str_replace(array_keys($Replace), $Replace, file_get_contents(TEMPLATE_DIR.'/fcselector.txt')));
    }