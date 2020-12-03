<?php

const URL = "https://www.apple.com/shop/refurbished/mac/13-inch-macbook-air";
const MATCH_EMAILS = "david.leibovic@gmail.com";
const ERROR_EMAILS = "david.leibovic@gmail.com";
$already_seen_urls = [];

while (true) {
    try {
        $doc = getDomDocument();
        $match_urls_to_desc_map = searchForMatches($doc);
        $new_match_urls_to_desc_map = removeAlreadySeenMatches($match_urls_to_desc_map, $already_seen_urls);
        if ($new_match_urls_to_desc_map) {
            echo "Found " . count($new_match_urls_to_desc_map) . " matches: " .  print_r($new_match_urls_to_desc_map, true) . "\n";
            sendMatchEmail($new_match_urls_to_desc_map);
            foreach ($new_match_urls_to_desc_map as $url => $desc) {
                $already_seen_urls[$url] = true;
            }
        } else {
            echo "No matches found.\n";
        }
    } catch (Exception $e) {
        echo "Got an exception: $e\n";
        sendErrorEmail($e);
    }
    sleep(480); // 8 minutes;
}


function getDomDocument() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $error_string = null;
    if ($response_code !== 200) {
        $error_string = curl_error($ch);
        $exception_message = "Got unexpected response: $response_code. Error string: $error_string.";
    }

    curl_close($ch);

    if ($error_string) {
        throw new Exception($exception_message);
    }

    $doc = new DOMDocument();
    @$doc->loadHTML($output);
    if (!$doc) {
        throw new Exception("unable to load url: " . URL . ".");
    }
    return $doc;
}

function searchForMatches($doc) {
    $xpath = new DOMXPath($doc);
    $result_nodes = $xpath->query("//div[@class='refurbished-category-grid-no-js']//li");
    echo "Found {$result_nodes->length} results to search through.\n";
    $match_urls_to_desc_map = [];
    foreach ($result_nodes as $result_node) {
        $description_nodes = (new DOMXPath($result_node->ownerDocument))->query(".//h3", $result_node);
        if ($description_nodes->length === 0) {
            continue;
        }

        // filter for macbook air results
        if (stripos($description_nodes->item(0)->nodeValue, 'air') === false) {
            continue;
        }

        $url = getUrlForSpecBoxNode($result_node);
        $match_urls_to_desc_map[$url] = trim($description_nodes->item(0)->nodeValue);
        continue;

        $price_nodes = (new DOMXPath($parent_node->ownerDocument))->query(".//span[@itemprop='price']", $parent_node);
        if ($price_nodes->length !== 1) {
            throw new Exception("Unexpected price nodes count for matches: $price_nodes->length.");
        }
        $price = trim($price_nodes->item(0)->nodeValue);
        $price = (int) str_replace(["$", ","], ["", ""], $price);
        // if ($price < 800) {
            $match_urls[] = getUrlForSpecBoxNode($spec_box_node);
            continue;
        // }

        $memory_match_result = 0;
        $storage_match_result = 0;

        foreach ($spec_box_node->childNodes as $spec_box_node_child) {
            $node_text = $spec_box_node_child->nodeValue;
            if (!$memory_match_result) {
                $memory_match_result = preg_match("/4GB.*memory/", $node_text);
            }
            if ($memory_match_result === false) {
                throw new Exception("Unable to do memory match regex.");
            }

            if (!$storage_match_result) {
                $storage_match_result = preg_match("/128GB.*storage/", $node_text);
            }
            if ($storage_match_result === false) {
                throw new Exception("Unable to do storage match regex.");
            }

            if (($memory_match_result && $storage_match_result)) {
                $match_urls[] = getUrlForSpecBoxNode($spec_box_node);
                break;
            }
        }
    }
    return $match_urls_to_desc_map;
}

function getUrlForSpecBoxNode($spec_box_node) {
    $url_nodes = (new DOMXPath($spec_box_node->ownerDocument))->query(".//a", $spec_box_node);
    if ($url_nodes->length !== 1) {
        throw new Exception("Unexpected url_nodes count for matches: $url_nodes->length.");
    }
    return "http://www.apple.com" . $url_nodes->item(0)->getAttribute('href');
}

function removeAlreadySeenMatches($match_urls_to_desc_map, $already_seen_urls) {
    $new_match_urls_to_desc_map = [];
    foreach ($match_urls_to_desc_map as $url => $desc) {
        if (!isset($already_seen_urls[$url])) {
            $new_match_urls_to_desc_map[$url] = $desc;
        } else {
            echo "Removing $url because it was already seen.\n";
        }
    }
    return $new_match_urls_to_desc_map;
}

function sendMatchEmail($new_match_urls_to_desc_map) {
    $to = MATCH_EMAILS;
    $subject = "omfg we found a macbook air for you!!!!!!!!!";
    $message = "URL(s):\n";
    foreach ($new_match_urls_to_desc_map as $url => $desc) {
        $message .= $desc . "\n" . $url . "\n\n";
    }
    $message .= "We found these matches at " . URL . "\n" .
        "Make sure the price is below the list price of $999.00\n";

    if (!mail($to, $subject, $message)) {
        throw new Exception("Unable to send match email.");
    }
}

function sendErrorEmail($e) {
    $to = ERROR_EMAILS;
    $subject = "macbook scraper error";
    $message = "$e";
    mail($to, $subject, $message);
}

