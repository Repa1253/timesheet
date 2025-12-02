(function() {
  const saveBtn = document.getElementById('timesheet_admin_save');
  if (!saveBtn) {
    return;
  }

  saveBtn.addEventListener('click', function() {
    const statusEl = document.getElementById('timesheet-admin-status');
    const hrGroupsEl = document.getElementById('timesheet_hr_groups');
    const hrGroupEl = document.getElementById('timesheet_hr_user_group');

    const hrGroups = hrGroupsEl?.value ?? '';
    const hrUserGroup = hrGroupEl?.value ?? '';

    statusEl.textContent = 'Speichere...';

    const formData = new FormData();
    formData.append('hrGroups', hrGroups);
    formData.append('hrUserGroup', hrUserGroup);
    
    fetch(OC.generateUrl('/apps/timesheet/admin/settings'), {
      method: 'POST',
      headers: {
        'requesttoken': OC.requestToken,
      },
      body: formData,
    })
    .then((response) => {
      if (!response.ok) {
        throw new Error('HTTP '+ response.status);
      }
      return response.json();
    })
    .then(() => {
      statusEl.textContent = 'Gespeichert';
    })
    .catch(err => {
      console.error(err);
      statusEl.textContent = 'Fehler beim Speichern';
    });
  });
})();