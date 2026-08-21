<?php
// Extracted from DevIAMCommand.php
echo "
            <div class='form-group'>
                <label>Username</label>
                <input type='text' name='username' class='spp-element' value='$username' required>
            </div>
            <div class='form-group'>
                <label>Email</label>
                <input type='email' name='email' class='spp-element' value='$email' required>
            </div>
            <div class='form-group'>
                <label>Status</label>
                <select name='status' class='spp-element'>
                    <option value='active' " . ($status === 'active' ? 'selected' : '') . ">Active</option>
                    <option value='inactive' " . ($status === 'inactive' ? 'selected' : '') . ">Inactive</option>
                </select>
            </div>
            " . (!$id ? "
            <div class='form-group'>
                <label>Password</label>
                <input type='password' name='password' class='spp-element' required>
            </div>" : "");
