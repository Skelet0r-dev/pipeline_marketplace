const profileBtn = document.getElementById('profileBtn');
const profileDropdown = document.getElementById('profileDropdown');
if(profileBtn && profileDropdown){
    profileBtn.addEventListener('click', function(e){
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });
    document.addEventListener('click', function(){
        profileDropdown.classList.remove('show');
    });
    profileDropdown.addEventListener('click', function(e){ e.stopPropagation(); });
}

const reportPanel = document.getElementById('userReportPanel');
const reportBackdrop = document.getElementById('userReportBackdrop');
const toggleReport = document.getElementById('toggleUserReport');
const closeReport = document.getElementById('closeUserReport');
const cancelReport = document.getElementById('cancelUserReport');
const reportForm = document.getElementById('userReportForm');
const reportFeedback = document.getElementById('userReportFeedback');
const submitReport = document.getElementById('submitUserReport');

function setReportVisible(visible){
    reportPanel.hidden = !visible;
    document.body.classList.toggle('public-report-open', visible);
}

function showReportFeedback(message, isError){
    reportFeedback.hidden = false;
    reportFeedback.textContent = message;
    reportFeedback.className = 'public-report-feedback ' + (isError ? 'is-error' : 'is-success');
}

toggleReport.addEventListener('click', function(){ setReportVisible(true); });
reportBackdrop.addEventListener('click', function(){ setReportVisible(false); });
closeReport.addEventListener('click', function(){ setReportVisible(false); });
cancelReport.addEventListener('click', function(){ setReportVisible(false); });

reportForm.addEventListener('submit', function(e){
    e.preventDefault();
    submitReport.disabled = true;
    submitReport.textContent = 'Submitting...';
    reportFeedback.hidden = true;

    fetch('report_user.php', { method: 'POST', body: new FormData(reportForm) })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(data.error){
                showReportFeedback(data.error, true);
                return;
            }
            showReportFeedback(data.message || 'Profile report submitted.', false);
            reportForm.reset();
        })
        .catch(function(){
            showReportFeedback('Could not submit your report right now. Please try again.', true);
        })
        .finally(function(){
            submitReport.disabled = false;
            submitReport.textContent = 'Submit Report';
        });
});