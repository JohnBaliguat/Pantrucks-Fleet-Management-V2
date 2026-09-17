/*
Template Name: Admin Template
Author: Wrappixel

File: js
*/
// ==============================================================
// Auto select left navbar + Persistent Sidebar State
// ==============================================================

$(function () {
  "use strict";
  var currentUrl = window.location.href;

  document.querySelectorAll("#sidebarnav a").forEach(function (link) {
    if (link.href === currentUrl) {
      link.classList.add("active");

      const parentUl = link.closest("ul");
      if (parentUl && parentUl.classList.contains("first-level")) {
        parentUl.classList.remove("collapse");
        parentUl.classList.add("show");
        parentUl.setAttribute("aria-expanded", "true");
      }
    }
  });

  // ================================
  // Click Toggle
  // ================================
  document.querySelectorAll("#sidebarnav a.has-arrow").forEach(function (link) {
    link.addEventListener("click", function (e) {
      const submenu = this.nextElementSibling;

      if (submenu && submenu.tagName === "UL") {
        e.preventDefault(); // prevent navigation for parent menu

        const isOpen = submenu.classList.contains("show");

        if (isOpen) {
          submenu.classList.remove("show");
          submenu.classList.add("collapse");
          this.classList.remove("active");
          submenu.setAttribute("aria-expanded", "false");
        } else {
          submenu.classList.remove("collapse");
          submenu.classList.add("show");
          this.classList.add("active");
          submenu.setAttribute("aria-expanded", "true");
        }
      }
    });
  });

});