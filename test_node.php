<?php
$xml = 'c:\projects\apache\school1\spp\modules\spp\sppxdb\data\default\users.xml';
$doc = new DOMDocument();
$doc->load($xml);
$xpath = new DOMXPath($doc);
$nodes = $xpath->query("//row");

function nodeToArray($node) {
    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return [];
    }
    $row = [];
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE) {
            $val = $child->nodeValue;
            $row[$child->nodeName] = $val;
        }
    }
    if ($node->hasAttributes()) {
        foreach ($node->attributes as $attr) {
            $row['@' . $attr->nodeName] = $attr->nodeValue;
            if ($attr->nodeName === 'id' && !isset($row['id'])) {
                $row['id'] = $attr->nodeValue;
            }
        }
    }
    return $row;
}

if ($nodes && $nodes->length > 0) {
    $row = nodeToArray($nodes->item(0));
    print_r($row);
    
    $keys = array_filter(array_keys($row), function ($k) {
        return $k[0] !== '@' && $k !== 'history';
    });
    print_r($keys);
    
    $results = [];
    if (in_array('id', $keys)) {
        $results[] = ['Field' => 'id'];
    }
    foreach ($keys as $k) {
        if ($k === 'id') continue;
        $results[] = ['Field' => $k];
    }
    print_r($results);
} else {
    echo "No nodes found\n";
}
