<?php
$caregiverConfigured = !empty($caregiver_policy);
$caregiverEnabled = $caregiverConfigured && (int) $caregiver_policy['enabled'] === 1;
$caregiverTypeId = $caregiverConfigured ? (int) $caregiver_policy['leave_type_id'] : 0;
?>

<div class="well hayne-statutory-policy" data-hayne-policy="caregiver">
    <div class="row-fluid">
        <div class="span7">
            <h3 style="margin-top: 0;">Urlop opiekuńczy</h3>
            <p>
                <strong>5 dni w roku kalendarzowym</strong> — oddzielna pula ustawowa,
                bez przenoszenia niewykorzystanych dni na kolejny rok.
            </p>
            <p class="muted" style="margin-bottom: 0;">
                HAYNE automatycznie tworzy pracownikom pulę 5 dni. Wniosek jest obsługiwany
                w pełnych dniach i wymaga danych osoby, której pracownik zapewnia opiekę lub wsparcie.
            </p>
        </div>
        <div class="span5">
            <?php echo form_open('haynelimits/saveCaregiverPolicy', [
                'class' => 'form-vertical',
                'id' => 'hayneCaregiverPolicyForm',
            ]); ?>
                <label for="caregiver_type_id">Rodzaj nieobecności w Jorani</label>
                <select name="caregiver_type_id" id="caregiver_type_id" class="input-xlarge" required>
                    <option value="">Wybierz rodzaj</option>
                    <?php foreach ($types as $type) {
                        $typeId = (int) $type['id'];
                        if ($typeId <= 0) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo $typeId; ?>" <?php echo $typeId === $caregiverTypeId ? 'selected' : ''; ?>>
                            <?php echo html_escape($type['name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <span class="help-block">Wybierz istniejący typ odpowiadający urlopowi opiekuńczemu. HAYNE nie zgaduje jego ID.</span>

                <label class="checkbox" for="caregiver_enabled">
                    <input type="checkbox" name="caregiver_enabled" id="caregiver_enabled" value="1" <?php echo $caregiverEnabled ? 'checked' : ''; ?> />
                    Aktywuj automatyczny limit 5 dni rocznie
                </label>

                <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
            </form>
        </div>
    </div>
</div>
