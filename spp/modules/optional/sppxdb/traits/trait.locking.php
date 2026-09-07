<?php

namespace SPPMod\SPPXDB;

trait XDB_Locking
{
    /**
     * Locks the current table file using a dedicated lockfile.
     *
     * @param int $mode LOCK_SH or LOCK_EX
     */
    protected function lock($mode)
    {
        if (!$this->filePath) {
            return;
        }
        if (!$this->lockHandle) {
            $lockPath = $this->filePath . '.lock';
            $this->lockHandle = fopen($lockPath, 'c+');
        }
        flock($this->lockHandle, $mode);
    }

    /**
     * Unlocks the current table lockfile.
     */
    protected function unlock()
    {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

}
