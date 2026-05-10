// Profile dropdown
var profileBtn = document.getElementById('profileBtn');
var profileDropdown = document.getElementById('profileDropdown');
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

// Tab switching
var tabs = document.querySelectorAll('.sf-tab');
tabs.forEach(function(tab){
    tab.addEventListener('click', function(){
        tabs.forEach(function(t){ t.classList.remove('active'); });
        this.classList.add('active');
        var target = this.dataset.tab;
        document.querySelectorAll('.sf-content').forEach(function(c){ c.classList.add('d-none'); });
        document.getElementById('tab-' + target).classList.remove('d-none');
    });
});

// Image preview (add listing)
var listingImgInput = document.getElementById('listingImgInput');
var imgPreview = document.getElementById('imgPreview');
var uploadBox = document.getElementById('uploadBox');
var uploadPrompt = document.getElementById('uploadPrompt');

listingImgInput.addEventListener('change', function(){
    var file = this.files[0];
    if(file){
        var reader = new FileReader();
        reader.onload = function(e){
            imgPreview.src = e.target.result;
            imgPreview.style.display = 'block';
            uploadPrompt.style.display = 'none';
            uploadBox.style.borderColor = '#606c38';
        };
        reader.readAsDataURL(file);
    }
});

// Open item detail modal
function openItemModal(card) {
    var title    = card.getAttribute('data-title');
    var price    = card.getAttribute('data-price');
    var category = card.getAttribute('data-category');
    var condition= card.getAttribute('data-condition');
    var condclass= card.getAttribute('data-condclass');
    var meetup   = card.getAttribute('data-meetup');
    var payment  = card.getAttribute('data-payment');
    var desc     = card.getAttribute('data-desc');
    var date     = card.getAttribute('data-date');
    var img      = card.getAttribute('data-img');
    var status   = card.getAttribute('data-status');
    var id       = card.getAttribute('data-id');

    document.getElementById('detailModalTitle').textContent = title;
    document.getElementById('detailTitle').textContent = title;
    document.getElementById('detailPrice').textContent = '₱' + price;
    document.getElementById('detailCategory').textContent = category;
    document.getElementById('detailMeetup').textContent = meetup;
    document.getElementById('detailPayment').textContent = payment ? payment : '—';
    document.getElementById('detailDate').textContent = date;
    document.getElementById('detailImg').src = img;
    document.getElementById('detailDesc').textContent = desc !== '' ? desc : 'No description provided.';
    document.getElementById('detailViewFull').href = 'listing.php?id=' + id;

    var condEl = document.getElementById('detailCond');
    condEl.textContent = condition;
    condEl.className = 'sf-card-cond ' + condclass;

    var statusEl = document.getElementById('detailStatus');
    if(status === 'Sold'){
        statusEl.textContent = 'SOLD';
        statusEl.className = 'detail-status-badge detail-status-sold';
    } else {
        statusEl.textContent = 'Available';
        statusEl.className = 'detail-status-badge detail-status-available';
    }

    var modal = new bootstrap.Modal(document.getElementById('itemDetailModal'));
    modal.show();
}

// Category → college toggle (add modal)
var categorySelect = document.getElementById('categorySelect');
var collegeRow = document.getElementById('collegeRow');
var collegeSelect = document.getElementById('collegeSelect');
var sectionRow = document.getElementById('sectionRow');

if(categorySelect) {
    categorySelect.addEventListener('change', function(){
        if(this.value == 'Course-Specific'){
            collegeRow.classList.remove('college-row-hidden');
            collegeSelect.setAttribute('required', 'required');
        } else {
            collegeRow.classList.add('college-row-hidden');
            sectionRow.classList.add('section-row-hidden');
            collegeSelect.removeAttribute('required');
            collegeSelect.value = '';
            document.getElementById('sectionInput').value = '';
        }
    });
}

if(collegeSelect) {
    collegeSelect.addEventListener('change', function() {
        if(this.value) sectionRow.classList.remove('section-row-hidden');
        else sectionRow.classList.add('section-row-hidden');
    });
}

// Edit modal category → college toggle
var editCategorySelect = document.getElementById('editCategory');
var editCollegeRow = document.getElementById('editCollegeRow');
var editCollegeSelect = document.getElementById('editCollege');
var editSectionRow = document.getElementById('editSectionRow');

if(editCategorySelect) {
    editCategorySelect.addEventListener('change', function(){
        if(this.value == 'Course-Specific'){
            editCollegeRow.classList.remove('college-row-hidden');
            editCollegeSelect.setAttribute('required', 'required');
        } else {
            editCollegeRow.classList.add('college-row-hidden');
            editSectionRow.classList.add('section-row-hidden');
            editCollegeSelect.removeAttribute('required');
            editCollegeSelect.value = '';
            document.getElementById('editSectionInput').value = '';
        }
    });
}

if(editCollegeSelect) {
    editCollegeSelect.addEventListener('change', function() {
        if(this.value) editSectionRow.classList.remove('section-row-hidden');
        else editSectionRow.classList.add('section-row-hidden');
    });
}

function confirmDelete() {
    return confirm('Are you sure you want to delete this listing? This cannot be undone.');
}

function normalizeCategoryValue(category) {
    if(!category) return '';
    if(category.indexOf('Course-Specific') === 0) return category;
    var categoryMap = {
        'Clothing and Apparel': 'Clothing & Apparel',
        'Hobbies and Lifestyle': 'Hobbies & Lifestyle',
        'Events and Tickets': 'Events & Tickets'
    };
    return categoryMap[category] || category;
}

// Open edit modal and pre-fill fields
function openEditModal(card) {
    var id       = card.getAttribute('data-id');
    var title    = card.getAttribute('data-title');
    var price    = card.getAttribute('data-price').replace(/,/g, '');
    var category = card.getAttribute('data-category');
    var condition= card.getAttribute('data-condition');
    var meetup   = card.getAttribute('data-meetup');
    var payment  = card.getAttribute('data-payment');
    var desc     = card.getAttribute('data-desc');
    var img      = card.getAttribute('data-img');
    var status   = card.getAttribute('data-status');

    document.getElementById('editListingId').value   = id;
    document.getElementById('deleteListingId').value = id;
    document.getElementById('editTitle').value       = title;
    document.getElementById('editDescription').value = desc;
    document.getElementById('editPrice').value       = price;
    document.getElementById('editCurrentImg').src    = img;

    var catSelect = document.getElementById('editCategory');
    var catVal = normalizeCategoryValue(category);
    
    if(category.indexOf('Course-Specific') === 0){
        catVal = 'Course-Specific';
        var collegeMatch = category.match(/\(([^)]+)\)/);
        if(collegeMatch){
            const parts = collegeMatch[1].split(' - ');
            const college = parts[0];
            const section = parts[1] || '';
            
            document.getElementById('editCollegeRow').classList.remove('college-row-hidden');
            setSelectValue(document.getElementById('editCollege'), college);
            document.getElementById('editSectionRow').classList.remove('section-row-hidden');
            document.getElementById('editSectionInput').value = section;
        }
    } else {
        document.getElementById('editCollegeRow').classList.add('college-row-hidden');
        document.getElementById('editSectionRow').classList.add('section-row-hidden');
    }

    setSelectValue(catSelect, catVal);
    setSelectValue(document.getElementById('editCondition'), condition);
    setSelectValue(document.getElementById('editMeetup'), meetup);
    setSelectValue(document.getElementById('editStatus'), status);
    setSelectValue(document.getElementById('editPayment'), payment);

    document.getElementById('editListingImgInput').value = '';
    document.getElementById('editImgPreview').style.display = 'none';
    document.getElementById('editUploadPrompt').style.display = 'block';

    var modal = new bootstrap.Modal(document.getElementById('editListingModal'));
    modal.show();
}

function setSelectValue(selectEl, val) {
    if(!selectEl) return;
    for(var i = 0; i < selectEl.options.length; i++){
        if(selectEl.options[i].value === val){
            selectEl.selectedIndex = i;
            break;
        }
    }
}

// Edit image preview
var editImgInput = document.getElementById('editListingImgInput');
var editImgPreview = document.getElementById('editImgPreview');
var editUploadPrompt = document.getElementById('editUploadPrompt');

editImgInput.addEventListener('change', function(){
    var file = this.files[0];
    if(file){
        var reader = new FileReader();
        reader.onload = function(e){
            editImgPreview.src = e.target.result;
            editImgPreview.style.display = 'block';
            editUploadPrompt.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
});
