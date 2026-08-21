<?php
// Extracted from AdminIAMCommand.php
echo "
            <div class='form-group'>
                <label>Field Name</label>
                <input type='text' name='name' class='spp-element' required>
            </div>
            <div class='form-group'>
                <label>Label</label>
                <input type='text' name='label' class='spp-element'>
            </div>
            <div class='form-group'>
                <label>Type</label>
                <select name='type' class='spp-element'>
                    <option value='text'>Text</option>
                    <option value='email'>Email</option>
                    <option value='password'>Password</option>
                    <option value='textarea'>Textarea</option>
                    <option value='select'>Dropdown (Select)</option>
                    <option value='checkbox'>Checkbox</option>
                    <option value='radio'>Radio</option>
                    <option value='date'>Date</option>
                    <option value='number'>Number</option>
                </select>
            </div>
            <div class='form-group'>
                <label>Placeholder</label>
                <input type='text' name='placeholder' class='spp-element'>
            </div>
            <div style='display: flex; gap: 10px; margin-top: 10px;'>
                <label style='display: flex; align-items: center; gap: 5px;'><input type='checkbox' name='required' class='spp-element'> Required</label>
                <label style='display: flex; align-items: center; gap: 5px;'><input type='checkbox' name='voice' class='spp-element'> Voice-to-Text</label>
                <label style='display: flex; align-items: center; gap: 5px;'><input type='checkbox' name='telemetry' class='spp-element'> Telemetry</label>
            </div>";
