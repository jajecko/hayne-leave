<?php
$forceMajeureConfigured = !empty($force_majeure_policy);
$forceMajeureEnabled = $forceMajeureConfigured && (int) $force_majeure_policy['enabled'] === 1;
$forceMajeureTypeId = $forceMajeureConfigured ? (int) $force_majeure_policy['leave_type_id'] : 0;
?>

<div class="well hayne-statutory-policy" data-hayne-policy="force_majeure">
    <div class="row-fluid">
        <div class="span7">
            <h3 style="margin-top: 0;">Siła wyższa</h3>
            <p>
                <strong>2 dni w roku kalendarzowym</strong> — oddzielna pula ustawowa,
                bez przenoszenia niewykorzystanych dni na kolejny rok.
            </p>
            <p class="muted">
                Dotyczy pilnych spraw rodzinnych spowodowanych chorobą lub wypadkiem,
                gdy niezbędna jest natychmiastowa obecność pracownika. Wniosek może być
                złożony najpóźniej w dniu korzystania ze zwolnienia.
            </p>
            <p class="muted" style="margin-bottom: 0;">
                HAYNE obsługuje wyłącznie wariant <strong>2 pełnych dni</strong>.
                Wariant godzinowy jest obsługiwany poza HAYNE przez HR. Pierwszy wniosek
                złożony w HAYNE w danym roku oznacza wybór wariantu dniowego. Za czas
                tego zwolnienia przysługuje połowa wynagrodzenia; HAYNE nie nalicza płac.
            </p>
        </div>
        <div class="span5">
            <?php echo form_open('haynelimits/saveForceMajeurePolicy', [
                'class' => 'form-vertical',
                'id' => 'hayneForceMajeurePolicyForm',
            ]); ?>
                <label for="force_majeure_type_id">Rodzaj nieobecności w Jorani</label>
                <select name="force_majeure_type_id" id="force_majeure_type_id" class="input-xlarge" required>
                    <option value="">Wybierz rodzaj</option>
                    <?php foreach ($types as $type) {
                        $typeId = (int) $type['id'];
                        if ($typeId <= 0) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo $typeId; ?>" <?php echo $typeId === $forceMajeureTypeId ? 'selected' : ''; ?>>
                            <?php echo html_escape($type['name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <span class="help-block">Wybierz istniejący typ odpowiadający zwolnieniu z powodu siły wyższej. HAYNE nie zgaduje jego ID.</span>

                <label class="checkbox" for="force_majeure_enabled">
                    <input type="checkbox" name="force_majeure_enabled" id="force_majeure_enabled" value="1" <?php echo $forceMajeureEnabled ? 'checked' : ''; ?> />
                    Aktywuj automatyczny limit 2 dni rocznie
                </label>

                <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
            </form>
        </div>
    </div>
</div>
