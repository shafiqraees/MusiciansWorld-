@props(['label' => 'Reason for this change', 'placeholder' => 'Required — recorded in the audit trail'])
<div class="a-reason">
  <label class="a-field">
    <span>{{ $label }}</span>
    <input class="a-input" name="reason" placeholder="{{ $placeholder }}" maxlength="500" required>
  </label>
</div>
