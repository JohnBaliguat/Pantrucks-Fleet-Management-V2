<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="/vite.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fleet Management - Pantrucks Inc.</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
  </head>
  <body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
      <div class="container">
        <a class="navbar-brand fw-bold" href="#">
          <img src="img/pantrucksLogo.png" alt="" style="width: 250px;">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
          <ul class="navbar-nav ms-auto">
            <li class="nav-item">
              <a class="nav-link" href="#features">Features</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#booking">Book Now</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#track">Track Container</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#contact">Contact</a>
            </li>
            <li class="nav-item">
              <a href="../login" class="btn btn-primary ms-lg-3 px-4">Login</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-6">
            <h1 class="display-4 fw-bold mb-4">PanTrucks Fleet Management System</h1>
            <p class="lead mb-4">Streamline your logistics operations with our comprehensive platform for dispatching, booking, and fleet inventory management.</p>
            <div class="d-flex gap-3 flex-wrap">
              <a hidden href="#track" class="btn btn-outline-light btn-lg px-4">Track Container</a>
              <a href="../login" class="btn btn-primary btn-lg px-4">Sign In</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Booking Section -->
    <section id="booking" class="booking-section py-5" hidden>
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-8">
            <div class="booking-card">
              <div class="text-center mb-4">
                <h2 class="fw-bold mb-3">Book Your Shipment</h2>
                <p class="text-muted">Fill in the details below to schedule your container shipment</p>
              </div>
              <form id="bookingForm">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="customerName" class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="customerName" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="customerEmail" class="form-label">Email Address *</label>
                    <input type="email" class="form-control" id="customerEmail" required>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="customerPhone" class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control" id="customerPhone" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="companyName" class="form-label">Company Name</label>
                    <input type="text" class="form-control" id="companyName">
                  </div>
                </div>
                <hr class="my-4">
                <h5 class="mb-3">Shipment Details</h5>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="pickupLocation" class="form-label">Pickup Location *</label>
                    <input type="text" class="form-control" id="pickupLocation" placeholder="City, State" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="deliveryLocation" class="form-label">Delivery Location *</label>
                    <input type="text" class="form-control" id="deliveryLocation" placeholder="City, State" required>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="pickupDate" class="form-label">Pickup Date *</label>
                    <input type="date" class="form-control" id="pickupDate" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="containerType" class="form-label">Container Type *</label>
                    <select class="form-select" id="containerType" required>
                      <option value="">Select container type</option>
                      <option value="20ft">20ft Standard</option>
                      <option value="40ft">40ft Standard</option>
                      <option value="40ft-hc">40ft High Cube</option>
                      <option value="refrigerated">Refrigerated</option>
                    </select>
                  </div>
                </div>
                <div class="mb-3">
                  <label for="cargoDescription" class="form-label">Cargo Description *</label>
                  <textarea class="form-control" id="cargoDescription" rows="3" placeholder="Describe the cargo to be shipped" required></textarea>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="cargoWeight" class="form-label">Estimated Weight (kg) *</label>
                    <input type="number" class="form-control" id="cargoWeight" placeholder="0" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="specialRequirements" class="form-label">Special Requirements</label>
                    <input type="text" class="form-control" id="specialRequirements" placeholder="Optional">
                  </div>
                </div>
                <div class="alert alert-info">
                  <i class="bi bi-info-circle me-2"></i>
                  <small>Once submitted, our team will review your booking and contact you within 24 hours with a quote and confirmation.</small>
                </div>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                  <button type="reset" class="btn btn-outline-secondary px-5">Reset</button>
                  <button type="submit" class="btn btn-primary px-5">
                    <i class="bi bi-check-circle me-2"></i>Submit Booking
                  </button>
                </div>
              </form>

              <!-- Success Message -->
              <div id="bookingSuccess" class="alert alert-success mt-4" style="display: none;">
                <h5 class="alert-heading"><i class="bi bi-check-circle me-2"></i>Booking Submitted Successfully!</h5>
                <p>Your booking reference number is: <strong id="bookingReference"></strong></p>
                <p class="mb-0">We will contact you shortly to confirm your shipment details.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section py-5" hidden>
      <div class="container">
        <div class="text-center mb-5">
          <h2 class="fw-bold mb-3">Our Services</h2>
          <p class="text-muted">Everything you need to manage your fleet efficiently</p>
        </div>
        <div class="row g-4">
          <div class="col-md-4">
            <div class="feature-card">
              <div class="feature-icon">
                <i class="bi bi-geo-alt"></i>
              </div>
              <h3>Smart Dispatching</h3>
              <p>Optimize routes and manage your fleet in real-time with our intelligent dispatching system.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="feature-card">
              <div class="feature-icon">
                <i class="bi bi-calendar-check"></i>
              </div>
              <h3>Booking System</h3>
              <p>Streamlined booking process for seamless container reservations and scheduling.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="feature-card">
              <div class="feature-icon">
                <i class="bi bi-box-seam"></i>
              </div>
              <h3>Fleet Inventory</h3>
              <p>Complete visibility of your fleet assets with comprehensive inventory management.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Track Container Section -->
    <section id="track" class="track-section py-5" hidden>
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-12">
            <div class="track-card">
              <div class="text-center mb-4">
                <h2 class="fw-bold mb-3">Track Your Container</h2>
                <p class="text-muted">Enter your container number to view booking details and trip history</p>
              </div>
              <form id="trackForm">
                <div class="input-group input-group-lg mb-3">
                  <input
                    type="text"
                    class="form-control"
                    id="containerNumber"
                    placeholder="Enter container number (e.g., CONT123456)"
                    required
                  >
                  <button class="btn btn-primary px-5" type="submit">
                    <i class="bi bi-search me-2"></i>Track
                  </button>
                </div>
              </form>

              <!-- Results Container -->
              <div id="trackResults" class="mt-4" style="display: none;">
                <div class="alert alert-info">
                  <h5 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Container Details</h5>
                  <hr>
                  <div id="containerDetails"></div>
                </div>

                <h5 class="mt-4 mb-3">Trip History</h5>
                <div id="tripHistory" class="timeline"></div>
              </div>

              <!-- Error Message -->
              <div id="errorMessage" class="alert alert-warning mt-4" style="display: none;">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <span id="errorText"></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section py-5" hidden>
      <div class="container">
        <div class="row text-center g-4">
          <div class="col-md-3">
            <div class="stat-item">
              <h2 class="display-4 fw-bold">200+</h2>
              <p >Active Vehicles</p>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-item">
              <h2 class="display-4 fw-bold">100K+</h2>
              <p >Deliveries</p>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-item">
              <h2 class="display-4 fw-bold">99%</h2>
              <p >On-Time Rate</p>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-item">
              <h2 class="display-4 fw-bold">24/7</h2>
              <p >Support</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section py-5" hidden>
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-8 text-center">
            <h2 class="fw-bold mb-4">Ready to Optimize Your Fleet?</h2>
            <p class="lead mb-4">Get in touch with our team to learn how we can help transform your logistics operations.</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
              <a href="mailto:info@pantrucks.com" class="btn btn-primary btn-lg">
                <i class="bi bi-envelope me-2"></i>Contact Sales
              </a>
              <a href="tel:+1234567890" class="btn btn-outline-primary btn-lg">
                <i class="bi bi-telephone me-2"></i>Call Us
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-4">
      <div class="container">
        <div class="row" hidden>
          <div class="col-md-6">
            <h5 class="fw-bold mb-3">
              <i class="bi bi-truck text-primary"></i> Panabo Trucking Services Inc.
            </h5>
            <p class="text-muted">Modern fleet management solutions for the logistics industry.</p>
          </div>
          <div class="col-md-3">
            <h6 class="fw-bold mb-3">Quick Links</h6>
            <ul class="list-unstyled">
              <li><a href="#features" class="text-muted">Features</a></li>
              <li><a href="#track" class="text-muted">Track Container</a></li>
              <li><a href="#contact" class="text-muted">Contact</a></li>
            </ul>
          </div>
          <div class="col-md-3">
            <h6 class="fw-bold mb-3">Contact</h6>
            <ul class="list-unstyled text-muted">
              <li><i class="bi bi-envelope me-2"></i>pantrucks@anflocor.com</li>
              <li><i class="bi bi-telephone me-2"></i>+1 (234) 567-890</li>
            </ul>
          </div>
        </div>
        <hr class="my-4">
        <div class="text-center text-muted">
          <p>&copy; 2025 Pantrucks Inc. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <!-- Bootstrap JS -->
     <script src="../assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script type="module" src="main.js"></script>
  </body>
</html>
