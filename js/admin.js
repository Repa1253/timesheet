(function() {

  // Hilfsfunktion: AJAX-Request zum Speichern senden
  async function updateGroups(field, group, remove = false) {
    if (!group) return null;

    const url = OC.generateUrl(`/apps/timesheet/settings/${field}`);
    const formData = new FormData();
    formData.append('group', group);
    if (remove) {
      formData.append('remove', '1');
    }

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'requesttoken': OC.requestToken },
        body: formData,
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return await response.json();
    } catch (error) {
      console.error(`Update '${field}' failed:`, error);
      return null;
    }
  }

  function attachRemoveHandler(link) {
    link.addEventListener('click', async (event) => {
      event.preventDefault();
      const field = link.dataset.field;
      const group = link.dataset.group;
      if (!field || !group) return;

      const result = await updateGroups(field, group, true);
      if (result) {
        link.parentElement.remove();
      }
    });
  }

  // Handler für Auswahl im ersten Dropdown (hr_groups)
  const selectHrGroups = document.getElementById('timesheet_hr_groups_select');
  if (!selectHrGroups) return;
  selectHrGroups.addEventListener('change', async function() {
    const group = this.value;
    if (!group) return;
    if (document.querySelector(`#timesheet_hr_group_selected [data-group="${group}"]`)) {
      this.value = '';
      return;
    }

    const result = await updateGroups('hr_groups', group);
    if (result) {
      const chipContainer = document.getElementById('timesheet_hr_groups_selected');
      const span = document.createElement('span');
      span.className = 'chip';
      span.textContent = group + ' ';
      const removeLink = document.createElement('a');
      removeLink.href = '#';
      removeLink.className = 'remove-group';
      removeLink.dataset.field = 'hr_groups';
      removeLink.dataset.group = group;
      removeLink.textContent = '×';

      span.appendChild(removeLink);
      chipContainer.appendChild(span);
      attachRemoveHandler(removeLink);
    }
    this.value = '';
  });

  // Handler für Auswahl im zweiten Dropdown (hr_user_groups)
  const selectUserGroups = document.getElementById('timesheet_hr_user_groups_select');
  if (!selectUserGroups) return;
  selectUserGroups.addEventListener('change', async function() {
    const group = this.value;
    if (!group) return;
    if (document.querySelector(`#timesheet_hr_user_group_selected [data-group="${group}"]`)) {
      this.value = '';
      return;
    }
    const result = await updateGroups('hr_user_groups', group);
    if (result) {
      const chipContainer = document.getElementById('timesheet_hr_user_group_selected');
      const span = document.createElement('span');
      span.className = 'chip';
      span.textContent = group + ' ';
      const removeLink = document.createElement('a');
      removeLink.href = '#';
      removeLink.className = 'remove-group';
      removeLink.dataset.field = 'hr_user_groups';
      removeLink.dataset.group = group;
      removeLink.textContent = '×';

      span.appendChild(removeLink);
      chipContainer.appendChild(span);
      attachRemoveHandler(removeLink);
    }
    this.value = '';
  });

  // Initiale Zuweisung der Entfernen-Links
  document.querySelectorAll('.remove-group').forEach(attachRemoveHandler);
})();