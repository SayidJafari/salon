// public/js/admin/custom.js
"use strict";
document.addEventListener("DOMContentLoaded", function () {
    const { OverlayScrollbars } = OverlayScrollbarsGlobal;
    const leftAside = document.querySelector(".left-aside");
    const leftAsideHeight = window.getComputedStyle(leftAside).height;

    const rightAside = document.querySelector(".right-aside");
    const rightAsideHeight = window.getComputedStyle(rightAside).height;

    const windowHeight = window.innerHeight + 500;
    const navHeight =
        windowHeight > leftAsideHeight ? windowHeight : leftAsideHeight;

    document.querySelector(".left-aside .navigation").style.height = navHeight;
    OverlayScrollbars(document.getElementById("menu"), {
        className: "os-theme-light",
        scrollbars: {
            x: "none",
            y: "auto",
        },
    });
    document
        .querySelector("#menu .navbar-brand")
        .querySelector("h1").innerHTML = "<h1 class='navbrand'>J</h1>";

    document
        .querySelector(".left-aside")
        .addEventListener("mouseenter", function () {
            document.querySelector("#menu .navbar-brand").style.marginLeft =
                "0";
            document.querySelector("#menu .navbar-brand").style.transition =
                "margin-left 0.3s linear";
            document.querySelector("#menu .navbar-brand h1").innerHTML =
                "<h1 class='text-center'>JOSH</h1>";
        });
    document
        .querySelector(".left-aside")
        .addEventListener("mouseleave", function () {
            document.querySelector("#menu .navbar-brand").style.marginLeft =
                "-175px";
            document.querySelector("#menu .navbar-brand").style.transition =
                "margin-left 0.3s linear";
            document.querySelector("#menu .navbar-brand h1").innerHTML =
                "<h1 class='navbrand'>J</h1>";
        });
    document
        .querySelector(".toggle-right")
        .addEventListener("click", function () {
            document.querySelector(".left-aside .sidebar").style.marginLeft =
                "0";
            document
                .querySelector(".left-aside .sidebar")
                .classList.remove("sidebar-res");
            document.querySelector("#menu .navbar-brand h1").innerHTML =
                "<h1 class='text-center'>JOSH</h1>";
            document.querySelector("#menu .navbar-brand").style.marginLeft =
                "0";

            document.querySelector(".close-icon").style.display =
                "inline-block";
        });

    document
        .querySelector(".close-icon")
        .addEventListener("click", function () {
            document.querySelector(".left-aside .sidebar").style.marginLeft =
                "-175px";
            document
                .querySelector(".left-aside .sidebar")
                .classList.add("sidebar-res");
            document.querySelector("#menu .navbar-brand h1").innerHTML =
                "<h1 class='text-center me-10'>J</h1>";
            this.style.display = "none";
        });
});
//leftmenu collapse in active
document.addEventListener("DOMContentLoaded", function () {
    var menuLinks = document.querySelectorAll("#menu ul a");
    menuLinks.forEach(function (link) {
        if (link.getAttribute("href") === location.href) {
            const menuDropdown = link.parentElement.closest(".menu-dropdown");
            if (menuDropdown !== null) {
                const imIcon = menuDropdown.querySelector(".imicon");
                if (imIcon !== null) {
                    imIcon.classList.add("imarrow");
                }
            }
            return false;
        }
    });
});
//left menu collapse active end

//card collapse code start
document.querySelectorAll(".card-header .clickable").forEach((element) => {
    element.addEventListener("click", function (event) {
        const target = event.target;
        if (target.classList.contains("clickable")) {
            event.preventDefault();
            const card = target.closest(".card");
            const cardBody = card.querySelector(".card-body");

            if (!target.classList.contains("panel-collapsed")) {
                cardBody.style.display = "none";
                target.classList.add("panel-collapsed");
                target.classList.remove("fa-chevron-up");
                target.classList.add("fa-chevron-down");
                target.setAttribute("title", "Show Panel content");
                card.querySelector(".card-header").classList.remove("border");
            } else {
                cardBody.style.display = "block";
                target.classList.remove("panel-collapsed");
                target.classList.remove("fa-chevron-down");
                target.classList.add("fa-chevron-up");
                target.setAttribute("title", "Show Panel content");
                card.querySelector(".card-header").classList.remove("border");
            }
        }
    });
});

document.addEventListener("click", function (event) {
    const removeButton = event.target.closest(".removepanel");
    if (!removeButton) return;
    const cardToRemove = removeButton.closest(".card");
    cardToRemove.style.display = "none";
});
//card collapse code end
