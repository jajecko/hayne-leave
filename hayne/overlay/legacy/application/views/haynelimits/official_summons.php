<?php
$officialSummonsConfigured = !empty($official_summons_policy);
$officialSummonsEnabled = $officialSummonsConfigured && (int) $official_summons_policy['enabled'] === 1;
$officialSummonsTypeId = $officialSummonsConfigured ? (int) $official_summons_policy['leave_type_id'] : 0;
?>

<div class="well hayne-statutory-policy" data-hayne-policy="official_summons">
    <div class="row-fluid">
        <div class="span7">
            <h3 style="margin-top: 0;">Wezwanie sądu / urzędu / innego organu</h3>
            <p>
                <strong>Bez rocznej puli dni.</strong> Ten rodzaj zwolnienia nie może być blokowany tylko dlatego,
                że saldo Jorani wynosi 0 dni.
            </p>
            <p class="muted" style="margin-bottom: 0;">
                HAYNE nadal waliduje daty, okres umowy, kolizje z innymi wnioskami i zasady pełnych dni.
                Wyjątek dotyczy wyłącznie kontroli dostępnego salda dla jawnie wskazanego typu.
            </p>
        </div>
        <div class="span5">
            <?php echo form_open('haynelimits/saveOfficialSummonsPolicy', [
                'class' => 'form-vertical',
                'id' => 'hayneOfficialSummonsPolicyForm',
            ]); ?>
                <label for="official_summons_type_id">Rodzaj nieobecności w Jorani</label>
                <select name="official_summons_type_id" id="official_summons_type_id" class="input-xlarge" required>
                    <option value="">Wybierz rodzaj</option>
                    <?php foreach ($types as $type) {
                        $typeId = (int) $type['id'];
                        if ($typeId <= 0) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo $typeId; ?>" <?php echo $typeId === $officialSummonsTypeId ? 'selected' : ''; ?>>
                            <?php echo html_escape($type['name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <span class="help-block">Wybierz istniejący typ odpowiadający wezwaniu sądu, urzędu lub innego organu. HAYNE nie rozpoznaje go po nazwie ani nie zgaduje jego ID.</span>

                <label class="checkbox" for="official_summons_enabled">
                    <input type="checkbox" name="official_summons_enabled" id="official_summons_enabled" value="1" <?php echo $officialSummonsEnabled ? 'checked' : ''; ?> />
                    Nie wymagaj salda dla tego rodzaju nieobecności
                </label>

                <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
            </form>
        </div>
    </div>
</div>
