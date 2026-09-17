// Smooth scrolling for navigation links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      target.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  });
});

// Navbar background change on scroll
window.addEventListener('scroll', function() {
  const navbar = document.querySelector('.navbar');
  if (window.scrollY > 50) {
    navbar.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
  } else {
    navbar.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.05)';
  }
});

// === Container Tracking Section ===
const trackForm = document.getElementById('trackForm');
const containerNumberInput = document.getElementById('containerNumber');
const trackResults = document.getElementById('trackResults');
const errorMessage = document.getElementById('errorMessage');
const containerDetails = document.getElementById('containerDetails');
const tripHistory = document.getElementById('tripHistory');

trackForm.addEventListener('submit', function(e) {
  e.preventDefault();

  const containerNumber = containerNumberInput.value.trim().toUpperCase();

  // Hide previous results
  trackResults.style.display = 'none';
  errorMessage.style.display = 'none';

  // Show loading spinner
  const spinner = document.createElement('div');
  spinner.className = 'spinner-border text-primary mt-3';
  spinner.role = 'status';
  spinner.innerHTML = '<span class="visually-hidden">Loading...</span>';
  trackResults.parentElement.appendChild(spinner);

  // Fetch data from backend PHP
  fetch(`../php/operations/track_container.php?container=${containerNumber}`)
    .then(res => res.json())
    .then(data => {
      spinner.remove();

      if (data.status === 'success') {
        displayContainerData(data);
      } else {
        showError(data.message);
      }
    })
    .catch(err => {
      spinner.remove();
      showError('Error fetching data. Please try again later.');
      console.error('Fetch Error:', err);
    });
});

function displayContainerData(data) {
  const records = data.records;

  // Clear previous content
  containerDetails.innerHTML = '';
  tripHistory.innerHTML = '';

  // Display the latest record as container summary
  const latest = records[0];
  containerDetails.innerHTML = `
    <div class="row">
      <div class="col-md-6 mb-2"><strong>Container:</strong> ${latest.container_name}</div>
      <div class="col-md-6 mb-2"><strong>Status:</strong>
        <span class="badge ${latest.container_status === 'Delivered' ? 'bg-success' : 'bg-primary'}">
          ${latest.container_status}
        </span>
      </div>
      <div class="col-md-6 mb-2"><strong>Location:</strong> ${latest.container_location}</div>
      <div class="col-md-6 mb-2"><strong>Truck No:</strong> ${latest.truck_no || 'N/A'}</div>
      <div class="col-md-6 mb-2"><strong>Trailer No:</strong> ${latest.trailer_no || 'N/A'}</div>
      <div class="col-md-6 mb-2"><strong>Genset No:</strong> ${latest.genset_no || 'N/A'}</div>
      <div class="col-md-6 mb-2"><strong>Driver:</strong> ${latest.driver_name || 'N/A'}</div>
      <div class="col-md-6 mb-2"><strong>Trip Status:</strong>
        <span class="badge ${latest.trip_status === 'Done' ? 'bg-success' : 'bg-info'}">
          ${latest.trip_status}
        </span>
      </div>
      <div class="col-md-6 mb-2"><strong>Last Update:</strong> ${latest.Date}</div>
    </div>
  `;

  // Display activity history
  if (records.length > 0) {
    records.forEach(rec => {
      const item = document.createElement('div');
      item.className = 'timeline-item';
      item.innerHTML = `
        <div class="timeline-dot"></div>
        <div class="timeline-content">
          <div class="timeline-date" style="font-size: 18px;"><i class="bi bi-clock me-1"></i>${rec.Date}</div>
          <div class="timeline-location" style="font-size: 18px;"><i class="bi bi-geo-alt me-1"></i>${rec.container_location}</div>
          <div class="timeline-status">
            <span style="font-size: 18px;" class="badge ${rec.container_status === 'LOADED' ? 'bg-success' : 'bg-warning'}">
              ${rec.container_status}
            </span>
            <span class="ms-2" style="font-size: 18px; font-weight: 600;">${rec.trip_status}</span>
          </div>
          <div class="timeline-extra mt-2 text-muted small" style="display:flex;">
          
            <div style="margin-right: 50px; font-size: 18px;"><strong>Truck No:</strong> ${rec.truck_no || 'N/A'}</div>
            <div style="margin-right: 50px; font-size: 18px;"><strong>Trailer No:</strong> ${rec.trailer_no || 'N/A'}</div>
            <div style="margin-right: 50px; font-size: 18px;"><strong>Genset No:</strong> ${rec.genset_no || 'N/A'}</div>
            <div style="margin-right: 50px; font-size: 18px;"><strong>Driver:</strong> ${rec.driver_name || 'N/A'}</div>
        </div>
      `;
      tripHistory.appendChild(item);
    });
  } else {
    tripHistory.innerHTML = `<div class="text-muted">No activity history found for this container.</div>`;
  }

  trackResults.style.display = 'block';
}

function showError(message) {
  document.getElementById('errorText').textContent = message;
  errorMessage.style.display = 'block';
}

// === Animation for feature cards on scroll ===
const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '0';
      entry.target.style.transform = 'translateY(30px)';
      setTimeout(() => {
        entry.target.style.transition = 'all 0.6s ease-out';
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }, 100);
      observer.unobserve(entry.target);
    }
  });
}, observerOptions);

document.querySelectorAll('.feature-card').forEach(card => observer.observe(card));
document.querySelectorAll('.stat-item').forEach(item => observer.observe(item));

// === Booking Form Simulation ===
const bookingForm = document.getElementById('bookingForm');
const bookingSuccess = document.getElementById('bookingSuccess');

if (bookingForm) {
  bookingForm.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = {
      customerName: document.getElementById('customerName').value,
      customerEmail: document.getElementById('customerEmail').value,
      customerPhone: document.getElementById('customerPhone').value,
      companyName: document.getElementById('companyName').value,
      pickupLocation: document.getElementById('pickupLocation').value,
      deliveryLocation: document.getElementById('deliveryLocation').value,
      pickupDate: document.getElementById('pickupDate').value,
      containerType: document.getElementById('containerType').value,
      cargoDescription: document.getElementById('cargoDescription').value,
      cargoWeight: document.getElementById('cargoWeight').value,
      specialRequirements: document.getElementById('specialRequirements').value
    };

    const bookingReference = 'BK' + Date.now().toString().slice(-8);

    setTimeout(() => {
      bookingForm.style.display = 'none';
      bookingSuccess.style.display = 'block';
      document.getElementById('bookingReference').textContent = bookingReference;

      bookingSuccess.scrollIntoView({ behavior: 'smooth', block: 'center' });

      setTimeout(() => {
        bookingForm.reset();
        bookingForm.style.display = 'block';
        bookingSuccess.style.display = 'none';
      }, 5000);
    }, 500);

    console.log('Booking submitted:', formData);
    console.log('Booking Reference:', bookingReference);
  });
}

// === Login button (temporary handler) ===
const loginBtn = document.getElementById('loginBtn');
if (loginBtn) {
  loginBtn.addEventListener('click', function(e) {
    e.preventDefault();
    alert('Login functionality will be implemented soon. Please contact our team for access.');
  });
}

console.log('Fleet Management System initialized');
