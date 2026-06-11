<?php

namespace SPPMod\SPPXDB;

trait XDB_Views
{
    public function dropView($name)
    {
        if (isset($this->views[$name])) {
            unset($this->views[$name]);
            $viewPath = $this->dataDir . '/_views.json';
            file_put_contents($viewPath, json_encode($this->views, JSON_PRETTY_PRINT));

            $cachePath = $this->dataDir . '/_mview_' . $name . '.json';
            if (file_exists($cachePath)) {
                @unlink($cachePath);
            }
            return true;
        }
        return false;
    }

    protected function loadViews()
    {
        $viewPath = $this->dataDir . '/_views.json';
        if (file_exists($viewPath)) {
            $this->views = json_decode(file_get_contents($viewPath), true) ?: [];
        }
    }

    public function createView($name, $sql, $materialized = false)
    {
        $this->views[$name] = [
            'sql' => $sql,
            'materialized' => $materialized,
            'last_refresh' => null
        ];
        if ($materialized) {
            $this->refreshView($name);
        }

        $viewPath = $this->dataDir . '/_views.json';
        file_put_contents($viewPath, json_encode($this->views, JSON_PRETTY_PRINT));
        return true;
    }

    public function refreshView($name)
    {
        if (!isset($this->views[$name])) {
            return false;
        }
        $sql = $this->views[$name]['sql'];
        $data = $this->querySQL($sql);

        $cachePath = $this->dataDir . '/_mview_' . $name . '.json';
        file_put_contents($cachePath, json_encode($data));

        $this->views[$name]['last_refresh'] = time();
        $viewPath = $this->dataDir . '/_views.json';
        file_put_contents($viewPath, json_encode($this->views, JSON_PRETTY_PRINT));
        return true;
    }

}
