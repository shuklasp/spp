<?php

namespace SPPMod\SPPXDB;

trait XDB_Locking
{
    /**
     * Locks the current table file.
     *
     * @param int $mode LOCK_SH or LOCK_EX
     */
    protected function lock($mode)
    {
        if (!$this->filePath) {
            return;
        }
        if (!$this->lockHandle) {
            $this->lockHandle = fopen($this->filePath, 'c+');
        }
        flock($this->lockHandle, $mode);
    }

    /**
     * Unlocks the current table file.
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
