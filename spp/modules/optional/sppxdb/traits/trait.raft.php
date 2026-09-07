<?php

namespace SPPMod\SPPXDB;

trait XDB_Raft
{
    /**
     * Executes a FLWOR-Lite query (Native XQuery pattern).
     * Format: for $r in table where $r/field = 'val' return $r/field
     *
     * @param string $query
     * @return array
     */
    public function registerRemoteNode($url)
    {
        $this->remoteNodes[] = $url;
        return $this;
    }

    protected function queryRemoteNodes($sql)
    {
        $allRemoteResults = [];
        foreach ($this->remoteNodes as $nodeUrl) {
            $apiUrl = "$nodeUrl/api.php?action=query_xdb&sql=" . urlencode($sql);
            $response = @file_get_contents($apiUrl);
            if ($response) {
                $data = json_decode($response, true);
                if (is_array($data)) {
                    $allRemoteResults = array_merge($allRemoteResults, $data);
                }
            }
        }
        return $allRemoteResults;
    }

}
