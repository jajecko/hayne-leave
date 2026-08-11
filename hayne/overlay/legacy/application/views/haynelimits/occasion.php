<?php
$occasionConfigured = !empty($occasion_policy);
$occasionEnabled = $occasionConfigured && (int) $occasion_policy['enabled'] === 1;
$occasionTypeId = $occasionConfigured ? (int) $occasion_policy['leave_type_id'] : 0;
?>

<div class="well hayne-statutory-policy" data-hayne-policy="occasion">
    <div class="row-fluid">
        <div class="span7">
            <h3 style="margin-top: 0;">Urlop okolicznościowy</h3>
            <p>
                <strong>1 albo 2 dni na konkretne zdarzenie</strong>, zgodnie z katalogiem ustawowych
                okoliczności. To nie jest roczna pula i nie pomniejsza urlopu wypoczynkowego.
            </p>
            <p class="muted">
                HAYNE pilnuje limitu dla jednego zdarzenia na podstawie jego rodzaju i daty.
                Dwa dni można wykorzystać razem albo w dwóch oddzielnych wnioskach.
            </p>
            <p class="muted" style="margin-bottom: 0;">
                Dokument potwierdzający i związek terminu wolnego ze zdarzeniem weryfikuje przełożony
                lub HR. HAYNE nie przechowuje danych członka rodziny ani kopii dokumentów.
            </p>
        </div>
        <div class="span5">
            <?php echo form_open('haynelimits/saveOccasionPolicy', [
                'class' => 'form-vertical',
                'id' => 'hayneOccasionPolicyForm',
            ]); ?>
                <label for="occasion_type_id">Rodzaj nieobecności w Jorani</label>
                <select name="occasion_type_id" id="occasion_type_id" class="input-xlarge" required>
                    <option value="">Wybierz rodzaj</option>
                    <?php foreach ($types as $type) {
                        $typeId = (int) $type['id'];
                        if ($typeId <= 0) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo $typeId; ?>" <?php echo $typeId === $occasionTypeId ? 'selected' : ''; ?>>
                            <?php echo html_escape($type['name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <span class="help-block">Wybierz dedykowany typ dla urlopu okolicznościowego.</span>

                <label class="checkbox" for="occasion_enabled">
                    <input type="checkbox" name="occasion_enabled" id="occasion_enabled" value="1" <?php echo $occasionEnabled ? 'checked' : ''; ?> />
                    Aktywuj obsługę urlopu okolicznościowego
                </label>

                <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
            </form>
        </div>
    </div>
</div>
