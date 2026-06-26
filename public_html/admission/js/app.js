(() => {
  (() => {
    "use strict";
    var _a, _b, _c, _d, _e;
    const CONFIG = window.FIND_PILATES_CONFIG || {};
    const JOIN_FEE = Number(CONFIG.joinFee || 11e3);
    const TODAY = CONFIG.today || formatDate(/* @__PURE__ */ new Date());
    const SELECTABLE_START_DATE = CONFIG.selectableStartDate || TODAY;
    const WEEKDAY_LABELS = ["\u65E5", "\u6708", "\u706B", "\u6C34", "\u6728", "\u91D1", "\u571F"];
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const form = $("#joinForm");
    if (!form) return;
    const steps = $$(".form-step", form);
    let currentStep = 0;
    let submitting = false;
    const elements = {
      bar: $("#bar"),
      stepText: $("#stepText"),
      progressPct: $("#progressPct"),
      prevStep: $("#prevStep"),
      nextStep: $("#nextStep"),
      submitButton: $("#submitButton"),
      formError: $("#formError"),
      coursePanel: $("#coursePanel"),
      mainClubPanel: $("#mainClubPanel"),
      memberNumberPanel: $("#memberNumberPanel"),
      initialVisits: $("#initialVisits"),
      startDate: $("#startDate"),
      priceSummary: $("#priceSummary"),
      mobileSummary: $("#mobileSummary"),
      mobileSummaryToggle: $("#mobileSummaryToggle"),
      mobileSummaryTitle: $("#mobileSummaryTitle"),
      mobileSummaryTotal: $("#mobileSummaryTotal"),
      mobileSummaryBody: $("#mobileSummaryBody"),
      birth: $("#birth"),
      birthYear: $("#birthYear"),
      birthMonth: $("#birthMonth"),
      birthDay: $("#birthDay"),
      ageText: $("#ageText"),
      minorPanel: $("#minorPanel"),
      guardianName: $("#guardianName"),
      schoolPanel: $("#schoolPanel"),
      postalCode: $("#postalCode"),
      zipSearch: $("#zipSearch"),
      zipStatus: $("#zipStatus"),
      prefecture: $("#prefecture"),
      cityArea: $("#cityArea"),
      streetAddress: $("#streetAddress"),
      building: $("#building"),
      termsBox: $("#termsBox"),
      termsMeter: $("#termsMeter"),
      termsAgree: $("#termsAgree"),
      termsAgreeLabel: $("#termsAgreeLabel"),
      video: $("#video"),
      canvas: $("#canvas"),
      cameraStart: $("#cameraStart"),
      capture: $("#capture"),
      photoData: $("#photoData"),
      photoUpload: $("#photoUpload"),
      photoPreview: $("#photoPreview"),
      clientReview: $("#clientReview"),
      healthGroupError: $("#healthGroupError")
    };
    function yen(value) {
      const n = Math.max(0, Math.round(Number(value || 0)));
      return "".concat(n.toLocaleString("ja-JP"), "\u5186");
    }
    function formatDate(date) {
      const y = date.getFullYear();
      const m = String(date.getMonth() + 1).padStart(2, "0");
      const d = String(date.getDate()).padStart(2, "0");
      return "".concat(y, "-").concat(m, "-").concat(d);
    }
    function parseDate(value) {
      if (!value) return null;
      const date = new Date("".concat(value, "T00:00:00"));
      return Number.isNaN(date.getTime()) ? null : date;
    }
    function getUseType() {
      var _a2;
      return ((_a2 = $('input[name="use_type"]:checked', form)) == null ? void 0 : _a2.value) || "new";
    }
    function getMainStatus() {
      var _a2;
      return ((_a2 = $('input[name="main_member_status"]:checked', form)) == null ? void 0 : _a2.value) || "existing";
    }
    function selectedRadio(name) {
      return $('input[name="'.concat(cssEscape(name), '"]:checked'), form);
    }
    function initialVisitChoices(monthlyVisits) {
      if (Number(monthlyVisits) === 16) return [4, 8, 12, 16];
      return [2, 4, 6, 8];
    }
    function currentMonthlyVisits() {
      var _a2, _b2;
      if (getUseType() === "add") {
        return Number(((_a2 = selectedRadio("addon")) == null ? void 0 : _a2.dataset.visits) || 8);
      }
      return Number(((_b2 = selectedRadio("course")) == null ? void 0 : _b2.dataset.visits) || 8);
    }
    function selectedInitialVisits() {
      var _a2;
      return Number(((_a2 = elements.initialVisits) == null ? void 0 : _a2.value) || 0);
    }
    function initialFee(monthlyFee, monthlyVisits, initialVisits) {
      if (!monthlyFee || !monthlyVisits || !initialVisits) return 0;
      return Math.round(Number(monthlyFee) * (Number(initialVisits) / Number(monthlyVisits)));
    }
    function proratedMonthlyFee(monthlyFee, startDate) {
      var date = parseDate(startDate || "");
      if (!monthlyFee || !date) return 0;
      var daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
      var remainingDays = daysInMonth - date.getDate() + 1;
      return Math.round(Number(monthlyFee) * (remainingDays / daysInMonth));
    }
    function normalizeCampaignCode(code) {
      return String(code || "").trim().toUpperCase();
    }
    function campaignCodeValue() {
      var el = $("#campaignCode");
      return el ? el.value : "";
    }
    function isCampaignActive(campaign) {
      var _a2;
      var startDate = ((_a2 = elements.startDate) == null ? void 0 : _a2.value) || "";
      if (!campaign || !campaign.enabled || !startDate) return false;
      if (campaign.start_date && startDate < campaign.start_date) return false;
      if (campaign.end_date && startDate > campaign.end_date) return false;
      var code = normalizeCampaignCode(campaign.code || "");
      if (campaign.auto_apply && code === "") return true;
      return code !== "" && code === normalizeCampaignCode(campaignCodeValue());
    }
    function campaignTargetTotal(campaign, useType, monthlyVisits) {
      if (useType === "add") {
        if (Number(monthlyVisits) === 8) return Number(campaign.target_addon_basic_total || 0);
        if (Number(monthlyVisits) === 16) return Number(campaign.target_addon_double_total || 0);
        return null;
      }
      return Number(campaign.target_single_total || 0);
    }
    function campaignPlanKey(useType, monthlyVisits) {
      if (useType === "add") return Number(monthlyVisits) === 16 ? "addon_double" : "addon_basic";
      return Number(monthlyVisits) === 16 ? "single_double" : "single_basic";
    }
    function campaignRuleMatches(rule, planKey) {
      var scope = rule.scope || "all";
      return scope === "all" || scope === planKey;
    }
    function componentBaseAmount(components, component) {
      if (component === "initial_total") {
        return Object.keys(components).reduce(function(sum, key) {
          return sum + Number(components[key] || 0);
        }, 0);
      }
      return Number(components[component] || 0);
    }
    function ruleDiscountAmount(rule, baseAmount, remainingAmount) {
      if (baseAmount <= 0 || remainingAmount <= 0) return 0;
      if ((rule.discount_type || "amount") === "free") return remainingAmount;
      if ((rule.discount_type || "amount") === "target_amount") {
        return Math.min(remainingAmount, Math.max(0, baseAmount - Number(rule.amount || 0)));
      }
      if ((rule.discount_type || "amount") === "percent") {
        var rate = Math.min(100, Math.max(0, Number(rule.amount || 0)));
        return Math.min(remainingAmount, Math.round(remainingAmount * (rate / 100)));
      }
      return Math.min(remainingAmount, Math.max(0, Number(rule.amount || 0)));
    }
    function campaignUsesComponent(campaigns, component, planKey) {
      return campaigns.some(function(campaign) {
        if ((campaign.discount_mode || "amount") !== "rules") return false;
        return (campaign.discount_rules || []).some(function(rule) {
          return rule.enabled && rule.component === component && campaignRuleMatches(rule, planKey);
        });
      });
    }
    function campaignDiscountAmount(campaign, useType, monthlyVisits, regularInitialTotal) {
      if ((campaign.discount_mode || "amount") === "target_total") {
        var target = campaignTargetTotal(campaign, useType, monthlyVisits);
        return target === null ? 0 : Math.max(0, regularInitialTotal - target);
      }
      if ((campaign.discount_mode || "amount") === "percent") {
        var rate = Math.min(100, Math.max(0, Number(campaign.discount_rate || 0)));
        return Math.min(regularInitialTotal, Math.round(regularInitialTotal * (rate / 100)));
      }
      return Math.min(regularInitialTotal, Math.max(0, Number(campaign.discount_amount || 0)));
    }
    function appliedCampaignDiscounts(useType, monthlyVisits, regularInitialTotal, components) {
      var campaigns = Array.isArray(CONFIG.campaigns) ? CONFIG.campaigns : [];
      var candidates = [];
      var planKey = campaignPlanKey(useType, monthlyVisits);
      campaigns.forEach(function(campaign) {
        if (!isCampaignActive(campaign)) return;
        var amount = 0;
        var details = [];
        if ((campaign.discount_mode || "amount") === "rules") {
          var remainingByComponent = Object.assign({}, components, { initial_total: regularInitialTotal });
          (campaign.discount_rules || []).forEach(function(rule) {
            if (!rule.enabled || !campaignRuleMatches(rule, planKey)) return;
            var component = rule.component || "current_month_fee";
            var baseAmount = componentBaseAmount(components, component);
            var remaining = Number(remainingByComponent[component] || baseAmount);
            var ruleAmount = ruleDiscountAmount(rule, baseAmount, remaining);
            if (ruleAmount <= 0) return;
            amount += ruleAmount;
            remainingByComponent[component] = Math.max(0, remaining - ruleAmount);
            if (component !== "initial_total") {
              remainingByComponent.initial_total = Math.max(0, Number(remainingByComponent.initial_total || regularInitialTotal) - ruleAmount);
            }
            details.push({ component: component, amount: ruleAmount });
          });
        } else {
          amount = campaignDiscountAmount(campaign, useType, monthlyVisits, regularInitialTotal);
        }
        if (amount <= 0) return;
        candidates.push({
          name: campaign.name || "",
          code: campaign.code || "",
          amount: amount,
          details: details,
          combinable: Boolean(campaign.combinable)
        });
      });
      if (candidates.length === 0) return [];
      var allCombinable = candidates.every(function(campaign) {
        return campaign.combinable;
      });
      if (!allCombinable) {
        candidates.sort(function(a, b) {
          return b.amount - a.amount;
        });
        candidates[0].amount = Math.min(regularInitialTotal, candidates[0].amount);
        return [candidates[0]];
      }
      var remaining = regularInitialTotal;
      var applied = [];
      candidates.forEach(function(campaign) {
        var amount = Math.min(remaining, campaign.amount);
        if (amount <= 0) return;
        campaign.amount = amount;
        applied.push(campaign);
        remaining -= amount;
      });
      return applied;
    }
    function renderInitialVisitOptions() {
      if (!elements.initialVisits) return;
      const monthlyVisits = currentMonthlyVisits();
      const choices = initialVisitChoices(monthlyVisits);
      const selected = selectedInitialVisits();
      const nextSelected = choices.includes(selected) ? selected : choices[choices.length - 1];
      elements.initialVisits.innerHTML = choices.map((visits) => '<option value="'.concat(visits, '">').concat(visits, "\u56DE</option>")).join("");
      elements.initialVisits.value = String(nextSelected);
    }
    function calcFees() {
      const useType = getUseType();
      const joinFee = useType === "add" && getMainStatus() === "existing" ? 0 : JOIN_FEE;
      let label = "";
      let description = "";
      let monthlyVisits = 0;
      let initialVisits = 0;
      let monthlyFee = 0;
      let pilatesMonthlyFee = 0;
      let pilatesInitialFee = 0;
      let baseMonthlyFee = 0;
      let mainClubInitialFee = 0;
      let addonFee = 0;
      let addonInitialFee = 0;
      if (useType === "add") {
        const main = selectedRadio("main_membership");
        const addon = selectedRadio("addon");
        const mainLabel = (main == null ? void 0 : main.dataset.label) || "\u672C\u9928\u4F1A\u54E1\u672A\u9078\u629E";
        const addonLabel = (addon == null ? void 0 : addon.dataset.label) || "\u7A2E\u5225\u672A\u9078\u629E";
        baseMonthlyFee = Number((main == null ? void 0 : main.dataset.base) || 0);
        mainClubInitialFee = proratedMonthlyFee(baseMonthlyFee, (elements.startDate == null ? void 0 : elements.startDate.value) || "");
        addonFee = Number((addon == null ? void 0 : addon.dataset.fee) || 0);
        monthlyVisits = Number((addon == null ? void 0 : addon.dataset.visits) || 0);
        initialVisits = selectedInitialVisits() || monthlyVisits;
        addonInitialFee = initialFee(addonFee, monthlyVisits, initialVisits);
        pilatesMonthlyFee = addonFee;
        monthlyFee = baseMonthlyFee + addonFee;
        label = "".concat(mainLabel, " \uFF0B ").concat(addonLabel);
        description = (addon == null ? void 0 : addon.dataset.description) || "";
      } else {
        const course = selectedRadio("course");
        label = (course == null ? void 0 : course.dataset.label) || "\u30D9\u30FC\u30B7\u30C3\u30AF\u4F1A\u54E1";
        description = (course == null ? void 0 : course.dataset.description) || "";
        monthlyVisits = Number((course == null ? void 0 : course.dataset.visits) || 0);
        pilatesMonthlyFee = Number((course == null ? void 0 : course.dataset.fee) || 0);
        initialVisits = selectedInitialVisits() || monthlyVisits;
        pilatesInitialFee = initialFee(pilatesMonthlyFee, monthlyVisits, initialVisits);
        monthlyFee = pilatesMonthlyFee;
      }
      const activeCampaigns = (Array.isArray(CONFIG.campaigns) ? CONFIG.campaigns : []).filter(isCampaignActive);
      const planKey = campaignPlanKey(useType, monthlyVisits);
      const processingFee = Number(CONFIG.processingFee || 0);
      const pilatesCurrentMonthFee = pilatesInitialFee + addonInitialFee;
      const currentMonthFee = mainClubInitialFee + pilatesCurrentMonthFee;
      const nextMonthFee = pilatesMonthlyFee;
      const usesSplitCurrentMonth = useType === "add" && (campaignUsesComponent(activeCampaigns, "main_club_current_month_fee", planKey) || campaignUsesComponent(activeCampaigns, "pilates_current_month_fee", planKey));
      const usesSplitNextMonth = useType === "add" && (campaignUsesComponent(activeCampaigns, "main_club_next_month_fee", planKey) || campaignUsesComponent(activeCampaigns, "pilates_next_month_fee", planKey));
      const components = {
        join_fee: joinFee,
        processing_fee: processingFee
      };
      if (usesSplitCurrentMonth) {
        if (mainClubInitialFee > 0) {
          components.main_club_current_month_fee = mainClubInitialFee;
        }
        components.pilates_current_month_fee = pilatesCurrentMonthFee;
      } else {
        components.current_month_fee = currentMonthFee;
      }
      if (usesSplitNextMonth) {
        if (baseMonthlyFee > 0) {
          components.main_club_next_month_fee = baseMonthlyFee;
        }
        components.pilates_next_month_fee = nextMonthFee;
      } else if (campaignUsesComponent(activeCampaigns, "next_month_fee", planKey)) {
        components.next_month_fee = nextMonthFee;
      }
      const regularInitialTotal = Object.keys(components).reduce(function(sum, key) {
        return sum + Number(components[key] || 0);
      }, 0);
      const campaignDiscounts = appliedCampaignDiscounts(useType, monthlyVisits, regularInitialTotal, components);
      const campaignDiscount = campaignDiscounts.reduce(function(sum, campaign) {
        return sum + Number(campaign.amount || 0);
      }, 0);
      return {
        useType,
        label,
        description,
        monthlyVisits,
        initialVisits,
        monthlyFee,
        pilatesMonthlyFee,
        pilatesInitialFee,
        baseMonthlyFee,
        mainClubInitialFee,
        addonFee,
        addonInitialFee,
        processingFee,
        currentMonthFee,
        joinFee,
        mainClubCurrentMonthFee: components.main_club_current_month_fee || 0,
        pilatesCurrentMonthFee,
        nextMonthFee: components.next_month_fee || 0,
        mainClubNextMonthFee: components.main_club_next_month_fee || 0,
        pilatesNextMonthFee: components.pilates_next_month_fee || 0,
        regularInitialTotal,
        campaignDiscount,
        campaignDiscounts,
        initialTotal: Math.max(0, regularInitialTotal - campaignDiscount)
      };
    }
    function summaryRows(compact) {
      var _a2;
      const fees = calcFees();
      const startDateLabel = dateLabel(((_a2 = elements.startDate) == null ? void 0 : _a2.value) || "") || "\u672A\u9078\u629E";
      const rows = compact ? [
        ["\u9078\u629E\u30D7\u30E9\u30F3", fees.label],
        ["\u5229\u7528\u958B\u59CB\u65E5", startDateLabel],
        ["\u521D\u6708\u5229\u7528\u56DE\u6570", "".concat(fees.initialVisits, "\u56DE")],
        ["\u901A\u5E38\u6708\u4F1A\u8CBB", yen(fees.monthlyFee)]
      ] : [
        ["\u5229\u7528\u5F62\u614B", fees.useType === "add" ? "\u672C\u9928\u4F75\u7528" : "Find Pilates\u5358\u4F53"],
        ["\u9078\u629E\u30D7\u30E9\u30F3", fees.label],
        ["\u6708\u9593\u5229\u7528\u53EF\u80FD\u56DE\u6570", "".concat(fees.monthlyVisits, "\u56DE")],
        ["\u5229\u7528\u958B\u59CB\u5E0C\u671B\u65E5", startDateLabel],
        ["\u521D\u6708\u306E\u5229\u7528\u53EF\u80FD\u56DE\u6570", "".concat(fees.initialVisits, "\u56DE")],
        ["\u901A\u5E38\u6708\u4F1A\u8CBB", yen(fees.monthlyFee)]
      ];
      if (fees.useType === "add") {
        rows.push(["\u672C\u9928\u521D\u6708\u4F1A\u8CBB\uFF08\u65E5\u5272\uFF09", yen(fees.mainClubInitialFee)]);
        rows.push(["Find Pilates\u521D\u6708\u4F1A\u8CBB", yen(fees.pilatesCurrentMonthFee)]);
      } else {
        rows.push(["\u521D\u6708\u4F1A\u8CBB", yen(fees.pilatesCurrentMonthFee)]);
      }
      rows.push(["\u5165\u4F1A\u8CBB", yen(fees.joinFee)]);
      if (fees.useType === "add") {
        if (!compact) {
          rows.push(["\u672C\u9928\u6708\u4F1A\u8CBB", yen(fees.baseMonthlyFee)]);
          rows.push(["Find Pilates\u7A2E\u5225", yen(fees.addonFee)]);
        }
      }
      if (fees.processingFee > 0) {
        rows.push(["\u624B\u6570\u6599", yen(fees.processingFee)]);
      }
      if (fees.mainClubNextMonthFee > 0) {
        rows.push(["\u7FCC\u6708\u672C\u9928\u4F1A\u8CBB", yen(fees.mainClubNextMonthFee)]);
      }
      if (fees.pilatesNextMonthFee > 0) {
        rows.push(["\u7FCC\u6708Find Pilates\u7A2E\u5225", yen(fees.pilatesNextMonthFee)]);
      }
      if (fees.nextMonthFee > 0) {
        rows.push(["\u7FCC\u6708\u4F1A\u8CBB", yen(fees.nextMonthFee)]);
      }
      if (fees.campaignDiscount > 0) {
        rows.push(["\u30AD\u30E3\u30F3\u30DA\u30FC\u30F3\u5024\u5F15", "-".concat(yen(fees.campaignDiscount))]);
        var campaignLabels = (fees.campaignDiscounts || []).map(function(campaign) {
          return campaign.name || campaign.code || "\u30AD\u30E3\u30F3\u30DA\u30FC\u30F3";
        });
        if (compact) {
          rows.push(["\u9069\u7528\u30AD\u30E3\u30F3\u30DA\u30FC\u30F3", campaignLabels.join("\u3001")]);
        } else {
          campaignLabels.forEach(function(label) {
            rows.push(["\u9069\u7528\u30AD\u30E3\u30F3\u30DA\u30FC\u30F3", label]);
          });
        }
      }
      rows.push(["\u521D\u56DE\u6982\u7B97\u5408\u8A08", yen(fees.initialTotal)]);
      return rows;
    }
    function updatePrice() {
      var _a2, _b2, _c2;
      renderInitialVisitOptions();
      const useType = getUseType();
      const isAdd = useType === "add";
      (_a2 = elements.coursePanel) == null ? void 0 : _a2.classList.toggle("is-hidden", isAdd);
      (_b2 = elements.mainClubPanel) == null ? void 0 : _b2.classList.toggle("show", isAdd);
      (_c2 = elements.memberNumberPanel) == null ? void 0 : _c2.classList.toggle("show", isAdd && getMainStatus() === "existing");
      const rows = summaryRows(false);
      if (elements.priceSummary) {
        elements.priceSummary.innerHTML = rows.filter(([label, value]) => value !== "" && (value !== "0\u5186" || label === "\u521D\u56DE\u6982\u7B97\u5408\u8A08")).map(([label, value]) => "<div><dt>".concat(escapeHtml(label), "</dt><dd>").concat(escapeHtml(value), "</dd></div>")).join("");
      }
      const fees = calcFees();
      if (elements.mobileSummaryTitle) elements.mobileSummaryTitle.textContent = fees.label;
      if (elements.mobileSummaryTotal) elements.mobileSummaryTotal.textContent = yen(fees.initialTotal);
      if (elements.mobileSummaryBody) {
        const mobileRows = summaryRows(true).filter(([label, value]) => value !== "" && (value !== "0\u5186" || label === "\u521D\u56DE\u6982\u7B97\u5408\u8A08"));
        elements.mobileSummaryBody.innerHTML = mobileRows.map(([label, value]) => "<div><span>".concat(escapeHtml(label), "</span><strong>").concat(escapeHtml(value), "</strong></div>")).join("");
      }
      buildReview();
    }
    function dateLabel(value) {
      const date = parseDate(value);
      if (!date) return "";
      return "".concat(date.getFullYear(), "\u5E74").concat(date.getMonth() + 1, "\u6708").concat(date.getDate(), "\u65E5\uFF08").concat(WEEKDAY_LABELS[date.getDay()], "\uFF09");
    }
    function updateAge() {
      var _a2, _b2, _c2;
      syncBirthValue();
      const age = calcAge(((_a2 = elements.birth) == null ? void 0 : _a2.value) || "");
      const isMinor = age !== null && age < 18;
      const needsSchoolCheck = age !== null && age >= 15 && age <= 16;
      if (elements.ageText) {
        elements.ageText.textContent = age === null ? "\u5E74\u9F62\u3092\u81EA\u52D5\u8A08\u7B97\u3057\u307E\u3059\u3002" : "".concat(age, "\u6B73");
      }
      (_b2 = elements.minorPanel) == null ? void 0 : _b2.classList.toggle("show", isMinor);
      (_c2 = elements.schoolPanel) == null ? void 0 : _c2.classList.toggle("show", needsSchoolCheck);
      if (elements.guardianName) elements.guardianName.required = isMinor;
    }
    function syncBirthValue() {
      if (!elements.birth || !elements.birthYear || !elements.birthMonth || !elements.birthDay) return;
      updateBirthDayOptions();
      const year = elements.birthYear.value;
      const month = elements.birthMonth.value;
      const day = elements.birthDay.value;
      elements.birth.value = year && month && day ? "".concat(year, "-").concat(month, "-").concat(day) : "";
    }
    function updateBirthDayOptions() {
      if (!elements.birthYear || !elements.birthMonth || !elements.birthDay) return;
      const year = Number(elements.birthYear.value || 0);
      const month = Number(elements.birthMonth.value || 0);
      const selectedDay = elements.birthDay.value;
      const maxDay = year > 0 && month > 0 ? new Date(year, month, 0).getDate() : 31;
      elements.birthDay.innerHTML = '<option value="">\u65E5</option>';
      for (let day = 1; day <= maxDay; day += 1) {
        const value = String(day).padStart(2, "0");
        const option = document.createElement("option");
        option.value = value;
        option.textContent = "".concat(day, "\u65E5");
        elements.birthDay.appendChild(option);
      }
      if (Number(selectedDay) <= maxDay) {
        elements.birthDay.value = selectedDay;
      }
    }
    function calcAge(value) {
      const birth = parseDate(value);
      if (!birth) return null;
      const now = /* @__PURE__ */ new Date();
      let age = now.getFullYear() - birth.getFullYear();
      const monthDiff = now.getMonth() - birth.getMonth();
      if (monthDiff < 0 || monthDiff === 0 && now.getDate() < birth.getDate()) age -= 1;
      return age;
    }
    function updateProcedureSlots(index) {
      const dateInput = $("#procedureDate".concat(index));
      const timeSelect = $("#procedureTime".concat(index));
      const weekdayText = $("#procedureWeekday".concat(index));
      if (!dateInput || !timeSelect) return;
      const date = parseDate(dateInput.value);
      if (weekdayText) weekdayText.textContent = date ? "".concat(WEEKDAY_LABELS[date.getDay()], "\u66DC\u65E5") : "\u66DC\u65E5\u3092\u8868\u793A\u3057\u307E\u3059";
      const selected = timeSelect.value;
      const slots = date && date.getDay() === 0 ? CONFIG.sundaySlots : CONFIG.weekdaySlots;
      timeSelect.innerHTML = Object.entries(slots || {}).map(([value, label]) => '<option value="'.concat(escapeHtml(value), '">').concat(escapeHtml(label), "</option>")).join("");
      if (Object.prototype.hasOwnProperty.call(slots || {}, selected)) {
        timeSelect.value = selected;
      }
    }
    function validateScope(scope, options = {}) {
      var _a2, _b2, _c2, _d2, _e2, _f, _g;
      const messages = [];
      const invalids = [];
      const requirePhoto = (_a2 = options.requirePhoto) != null ? _a2 : false;
      $$(".field-error:not(.group-error)", scope).forEach((el) => el.remove());
      $$(".error", scope).forEach((el) => el.classList.remove("error"));
      if (elements.healthGroupError && scope.contains(elements.healthGroupError)) {
        elements.healthGroupError.hidden = true;
        elements.healthGroupError.textContent = "";
      }
      const required = $$("[required]", scope).filter((el) => !el.disabled && isVisible(el) && el.name !== "health_checks[]");
      required.forEach((el) => {
        if (!isFilled(el)) {
          addInvalid(el, labelFor(el) + "\u3092\u5165\u529B\u3057\u3066\u304F\u3060\u3055\u3044\u3002", messages, invalids);
        }
      });
      if (scope.contains(elements.startDate) && ((_b2 = elements.startDate) == null ? void 0 : _b2.value) < SELECTABLE_START_DATE) {
        addInvalid(elements.startDate, "利用開始希望日は2026年6月30日以降を選択してください。", messages, invalids);
      }
      if (scope.querySelector('[id^="procedureDate"]')) {
        validateProcedureDates(messages, invalids, scope);
      }
      if (scope.contains(elements.birthYear) || scope.contains(elements.birth)) {
        syncBirthValue();
      }
      if ((scope.contains(elements.birthYear) || scope.contains(elements.birth)) && ((_c2 = elements.birth) == null ? void 0 : _c2.value)) {
        const age = calcAge(elements.birth.value);
        if (elements.birth.value > TODAY) {
          addInvalid(elements.birthDay || elements.birth, "\u751F\u5E74\u6708\u65E5\u306B\u672A\u6765\u306E\u65E5\u4ED8\u306F\u6307\u5B9A\u3067\u304D\u307E\u305B\u3093\u3002", messages, invalids);
        } else if (age !== null && age < 15) {
          addInvalid(elements.birthDay || elements.birth, "15\u6B73\u672A\u6E80\u304A\u3088\u3073\u4E2D\u5B66\u751F\u4EE5\u4E0B\u306E\u65B9\u306F\u5165\u4F1A\u3067\u304D\u307E\u305B\u3093\u3002", messages, invalids);
        }
      }
      const kana = $("#kana");
      if (scope.contains(kana) && (kana == null ? void 0 : kana.value) && !/^[\u30a0-\u30ff\u3000\s]+$/u.test(kana.value.trim())) {
        addInvalid(kana, "\u30D5\u30EA\u30AC\u30CA\u306F\u5168\u89D2\u30AB\u30BF\u30AB\u30CA\u3067\u5165\u529B\u3057\u3066\u304F\u3060\u3055\u3044\u3002", messages, invalids);
      }
      const email = $("#email");
      if (scope.contains(email) && (email == null ? void 0 : email.value) && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        addInvalid(email, "\u30E1\u30FC\u30EB\u30A2\u30C9\u30EC\u30B9\u306E\u5F62\u5F0F\u3092\u78BA\u8A8D\u3057\u3066\u304F\u3060\u3055\u3044\u3002", messages, invalids);
      }
      const postal = elements.postalCode;
      if (scope.contains(postal) && (postal == null ? void 0 : postal.value) && postal.value.replace(/[^\d]/g, "").length !== 7) {
        addInvalid(postal, "\u90F5\u4FBF\u756A\u53F7\u306F7\u6841\u3067\u5165\u529B\u3057\u3066\u304F\u3060\u3055\u3044\u3002", messages, invalids);
      }
      ["phone", "emergency_phone"].forEach((name) => {
        const el = $('[name="'.concat(name, '"]'));
        if (scope.contains(el) && (el == null ? void 0 : el.value)) {
          const len = el.value.replace(/[^\d]/g, "").length;
          if (len < 10 || len > 11) {
            addInvalid(el, "".concat(labelFor(el), "\u306E\u5F62\u5F0F\u3092\u78BA\u8A8D\u3057\u3066\u304F\u3060\u3055\u3044\u3002"), messages, invalids);
          }
        }
      });
      if (getUseType() === "add" && scope.contains(elements.mainClubPanel)) {
        if (!selectedRadio("main_membership")) {
          addInvalid(elements.mainClubPanel, "\u672C\u9928\u4F1A\u54E1\u7A2E\u5225\u3092\u9078\u629E\u3057\u3066\u304F\u3060\u3055\u3044\u3002", messages, invalids);
        }
      }
      const healthChecks = $$('input[name="health_checks[]"]', scope);
      if (healthChecks.length > 0 && healthChecks.some((el) => !el.checked)) {
        const message = "\u5065\u5EB7\u72B6\u614B\u30FB\u5165\u4F1A\u8CC7\u683C\u306E\u78BA\u8A8D\u9805\u76EE\u3092\u3059\u3079\u3066\u30C1\u30A7\u30C3\u30AF\u3057\u3066\u304F\u3060\u3055\u3044\u3002";
        messages.push(message);
        invalids.push(healthChecks[0]);
        if (elements.healthGroupError) {
          elements.healthGroupError.textContent = message;
          elements.healthGroupError.hidden = false;
        }
      }
      if (requirePhoto) {
        const hasPhoto = Boolean((_d2 = elements.photoData) == null ? void 0 : _d2.value) || Boolean((_f = (_e2 = elements.photoUpload) == null ? void 0 : _e2.files) == null ? void 0 : _f.length);
        if (!hasPhoto) {
          addInvalid(elements.photoPreview, "\u9854\u5199\u771F\u3092\u64AE\u5F71\u307E\u305F\u306F\u30A2\u30C3\u30D7\u30ED\u30FC\u30C9\u3057\u3066\u304F\u3060\u3055\u3044\u3002", messages, invalids);
        }
      }
      if (scope.contains(elements.termsAgree) && !((_g = elements.termsAgree) == null ? void 0 : _g.checked)) {
        addInvalid(elements.termsAgreeLabel || elements.termsAgree, "\u30AF\u30E9\u30D6\u898F\u7D04\u3092\u6700\u5F8C\u307E\u3067\u78BA\u8A8D\u3057\u3001\u540C\u610F\u3057\u3066\u304F\u3060\u3055\u3044\u3002", messages, invalids);
      }
      return { valid: messages.length === 0, messages, invalids };
    }
    function validateProcedureDates(messages, invalids, scope) {
      const seen = /* @__PURE__ */ new Map();
      for (let i = 1; i <= 3; i += 1) {
        const dateInput = $("#procedureDate".concat(i));
        const timeSelect = $("#procedureTime".concat(i));
        if (!scope.contains(dateInput) || !(dateInput == null ? void 0 : dateInput.value)) continue;
        const date = parseDate(dateInput.value);
        if (!date) {
          addInvalid(dateInput, "\u7B2C".concat(i, "\u5E0C\u671B\u65E5\u306E\u65E5\u4ED8\u5F62\u5F0F\u3092\u78BA\u8A8D\u3057\u3066\u304F\u3060\u3055\u3044\u3002"), messages, invalids);
          continue;
        }
        if (dateInput.value < SELECTABLE_START_DATE) {
          addInvalid(dateInput, "第".concat(i, "希望日は2026年6月30日以降を選択してください。"), messages, invalids);
        }
        if ((CONFIG.closedDates || []).includes(dateInput.value) || (CONFIG.closedDayRules || []).includes(date.getDay())) {
          addInvalid(dateInput, "\u7B2C".concat(i, "\u5E0C\u671B\u65E5\u306F\u53D7\u4ED8\u4E0D\u53EF\u65E5\u3067\u3059\u3002"), messages, invalids);
        }
        const slots = date.getDay() === 0 ? CONFIG.sundaySlots : CONFIG.weekdaySlots;
        if ((timeSelect == null ? void 0 : timeSelect.value) && !Object.prototype.hasOwnProperty.call(slots || {}, timeSelect.value)) {
          addInvalid(timeSelect, "\u7B2C".concat(i, "\u5E0C\u671B\u306E\u6642\u9593\u5E2F\u304C\u55B6\u696D\u6642\u9593\u5916\u3067\u3059\u3002"), messages, invalids);
        }
        if (timeSelect == null ? void 0 : timeSelect.value) {
          const key = "".concat(dateInput.value, "|").concat(timeSelect.value);
          if (seen.has(key)) {
            addInvalid(timeSelect, "\u7B2C".concat(seen.get(key), "\u5E0C\u671B\u3068\u7B2C").concat(i, "\u5E0C\u671B\u304C\u540C\u3058\u65E5\u6642\u3067\u3059\u3002"), messages, invalids);
          } else {
            seen.set(key, i);
          }
        }
      }
    }
    function addInvalid(el, message, messages, invalids) {
      if (!el) return;
      if (!messages.includes(message)) messages.push(message);
      invalids.push(el);
      if (el.classList) el.classList.add("error");
      const target = el.closest(".choice, .check, .preference-row, .conditional, div") || el;
      const msg = document.createElement("p");
      msg.className = "field-error";
      msg.textContent = message;
      target.insertAdjacentElement("afterend", msg);
    }
    function labelFor(el) {
      var _a2;
      if (!(el == null ? void 0 : el.id)) return "\u3053\u306E\u9805\u76EE";
      const label = $('label[for="'.concat(cssEscape(el.id), '"]'));
      return ((_a2 = label == null ? void 0 : label.textContent) == null ? void 0 : _a2.replace(/必須|任意/g, "").trim()) || "\u3053\u306E\u9805\u76EE";
    }
    function isVisible(el) {
      return !el.closest(".conditional:not(.show), .course-panel.is-hidden, .form-step:not(.is-active)") || submitting;
    }
    function isFilled(el) {
      if (el.type === "checkbox") return el.checked;
      if (el.type === "radio") return Boolean(selectedRadio(el.name));
      return Boolean(String(el.value || "").trim());
    }
    function showStep(index) {
      var _a2;
      currentStep = Math.max(0, Math.min(index, steps.length - 1));
      steps.forEach((step, i) => step.classList.toggle("is-active", i === currentStep));
      const title = ((_a2 = steps[currentStep]) == null ? void 0 : _a2.dataset.stepTitle) || "";
      if (elements.stepText) elements.stepText.textContent = "STEP ".concat(currentStep + 1, " / ").concat(steps.length, "\u3000").concat(title);
      if (elements.progressPct) elements.progressPct.textContent = "".concat(currentStep + 1, " / ").concat(steps.length);
      if (elements.bar) elements.bar.style.width = "".concat((currentStep + 1) / steps.length * 100, "%");
      if (elements.prevStep) elements.prevStep.hidden = currentStep === 0;
      if (elements.nextStep) elements.nextStep.hidden = currentStep === steps.length - 1;
      if (elements.submitButton) elements.submitButton.hidden = currentStep !== steps.length - 1;
      clearTopError();
      if (currentStep === steps.length - 1) buildReview();
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
    function showTopError(messages, invalids) {
      if (!elements.formError) return;
      elements.formError.innerHTML = "<strong>\u5165\u529B\u5185\u5BB9\u3092\u78BA\u8A8D\u3057\u3066\u304F\u3060\u3055\u3044\u3002</strong><ul>".concat(messages.map((m) => "<li>".concat(escapeHtml(m), "</li>")).join(""), "</ul>");
      elements.formError.classList.add("show");
      const first = invalids.find(Boolean);
      first == null ? void 0 : first.scrollIntoView({ behavior: "smooth", block: "center" });
      if (typeof (first == null ? void 0 : first.focus) === "function") first.focus({ preventScroll: true });
    }
    function clearTopError() {
      var _a2;
      (_a2 = elements.formError) == null ? void 0 : _a2.classList.remove("show");
      if (elements.formError) elements.formError.innerHTML = "";
    }
    function buildReview() {
      var _a2, _b2, _c2, _d2, _e2, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o;
      if (!elements.clientReview) return;
      const rows = summaryRows();
      const prefs = [1, 2, 3].map((i) => {
        var _a3, _b3, _c3, _d3;
        const date = ((_a3 = $("#procedureDate".concat(i))) == null ? void 0 : _a3.value) || "";
        const time = ((_d3 = (_c3 = (_b3 = $("#procedureTime".concat(i))) == null ? void 0 : _b3.selectedOptions) == null ? void 0 : _c3[0]) == null ? void 0 : _d3.textContent) || "";
        return ["\u7B2C".concat(i, "\u5E0C\u671B"), "".concat(dateLabel(date) || "\u672A\u5165\u529B", " ").concat(time).trim()];
      });
      const contact = [
        ["\u6C0F\u540D", ((_a2 = $("#name")) == null ? void 0 : _a2.value) || "\u672A\u5165\u529B"],
        ["\u30D5\u30EA\u30AC\u30CA", ((_b2 = $("#kana")) == null ? void 0 : _b2.value) || "\u672A\u5165\u529B"],
        ["\u96FB\u8A71\u756A\u53F7", ((_c2 = $("#phone")) == null ? void 0 : _c2.value) || "\u672A\u5165\u529B"],
        ["\u30E1\u30FC\u30EB", ((_d2 = $("#email")) == null ? void 0 : _d2.value) || "\u672A\u5165\u529B"],
        ["\u4F4F\u6240", "".concat(((_e2 = $("#prefecture")) == null ? void 0 : _e2.value) || "").concat(((_f = $("#cityArea")) == null ? void 0 : _f.value) || "").concat(((_g = $("#streetAddress")) == null ? void 0 : _g.value) || "", " ").concat(((_h = $("#building")) == null ? void 0 : _h.value) || "").trim() || "\u672A\u5165\u529B"],
        ["\u7DCA\u6025\u9023\u7D61\u5148", "".concat(((_i = $("#emergencyName")) == null ? void 0 : _i.value) || "", "\uFF08").concat(((_j = $("#emergencyRelationship")) == null ? void 0 : _j.value) || "", "\uFF09 ").concat(((_k = $("#emergencyPhone")) == null ? void 0 : _k.value) || "").trim() || "\u672A\u5165\u529B"]
      ];
      elements.clientReview.innerHTML = [
        reviewBlock("\u5229\u7528\u5F62\u614B\u30FB\u6599\u91D1", rows, 0),
        reviewBlock("\u6765\u5E97\u5E0C\u671B\u65E5\u6642", prefs, 1),
        reviewBlock("\u304A\u5BA2\u69D8\u60C5\u5831", contact, 2),
        reviewBlock("\u5065\u5EB7\u72B6\u614B\u30FB\u898F\u7D04\u30FB\u9854\u5199\u771F", [
          ["\u5065\u5EB7\u78BA\u8A8D", checkedCount("health_checks[]") + "\u9805\u76EE\u78BA\u8A8D\u6E08\u307F"],
          ["\u898F\u7D04\u540C\u610F", ((_l = elements.termsAgree) == null ? void 0 : _l.checked) ? "\u540C\u610F\u6E08\u307F" : "\u672A\u540C\u610F"],
          ["\u9854\u5199\u771F", ((_m = elements.photoData) == null ? void 0 : _m.value) || ((_o = (_n = elements.photoUpload) == null ? void 0 : _n.files) == null ? void 0 : _o.length) ? "\u767B\u9332\u6E08\u307F" : "\u672A\u767B\u9332"]
        ], 3)
      ].join("");
      $$("[data-edit-step]", elements.clientReview).forEach((button) => {
        button.addEventListener("click", () => showStep(Number(button.dataset.editStep || 0)));
      });
    }
    function reviewBlock(title, rows, editStep) {
      return '<section class="review-block"><div class="review-head"><h3>'.concat(escapeHtml(title), '</h3><button class="btn ghost mini-btn" type="button" data-edit-step="').concat(editStep, '">\u4FEE\u6B63\u3059\u308B</button></div><dl>').concat(rows.map(([k, v]) => "<div><dt>".concat(escapeHtml(k), "</dt><dd>").concat(escapeHtml(v), "</dd></div>")).join(""), "</dl></section>");
    }
    function checkedCount(name) {
      return $$('input[name="'.concat(cssEscape(name), '"]:checked')).length;
    }
    function searchAddress() {
      var _a2, _b2;
      const digits = (((_a2 = elements.postalCode) == null ? void 0 : _a2.value) || "").replace(/[^\d]/g, "");
      if (!elements.zipStatus || !elements.postalCode) return;
      if (typeof fetch !== "function") {
        elements.zipStatus.textContent = "\u4F4F\u6240\u691C\u7D22\u306B\u5BFE\u5FDC\u3057\u3066\u3044\u306A\u3044\u7AEF\u672B\u3067\u3059\u3002\u624B\u5165\u529B\u3057\u3066\u304F\u3060\u3055\u3044\u3002";
        return;
      }
      if (digits.length !== 7) {
        elements.zipStatus.textContent = "\u90F5\u4FBF\u756A\u53F7\u306F7\u6841\u3067\u5165\u529B\u3057\u3066\u304F\u3060\u3055\u3044\u3002";
        elements.postalCode.classList.add("error");
        return;
      }
      elements.postalCode.value = digits;
      elements.zipStatus.textContent = "\u4F4F\u6240\u3092\u691C\u7D22\u3057\u3066\u3044\u307E\u3059\u3002";
      elements.zipSearch.disabled = true;
      fetch("https://zipcloud.ibsnet.co.jp/api/search?zipcode=".concat(encodeURIComponent(digits)), { cache: "no-store" }).then(function(response) {
        return response.json();
      }).then(function(data) {
        if (data.status !== 200 || !((_b2 = data.results) == null ? void 0 : _b2.length)) {
          elements.zipStatus.textContent = "\u8A72\u5F53\u3059\u308B\u4F4F\u6240\u304C\u898B\u3064\u304B\u308A\u307E\u305B\u3093\u3067\u3057\u305F\u3002\u624B\u5165\u529B\u3057\u3066\u304F\u3060\u3055\u3044\u3002";
          return;
        }
        const result = data.results[0];
        elements.prefecture.value = result.address1 || "";
        elements.cityArea.value = "".concat(result.address2 || "").concat(result.address3 || "");
        elements.streetAddress.focus();
        elements.zipStatus.textContent = data.results.length > 1 ? "\u8907\u6570\u5019\u88DC\u306E\u5148\u982D\u3092\u5165\u529B\u3057\u307E\u3057\u305F\u3002\u5FC5\u8981\u306B\u5FDC\u3058\u3066\u4FEE\u6B63\u3057\u3066\u304F\u3060\u3055\u3044\u3002" : "\u4F4F\u6240\u3092\u5165\u529B\u3057\u307E\u3057\u305F\u3002\u756A\u5730\u4EE5\u964D\u3092\u5165\u529B\u3057\u3066\u304F\u3060\u3055\u3044\u3002";
      }).catch(function() {
        elements.zipStatus.textContent = "\u4F4F\u6240\u691C\u7D22\u306B\u5931\u6557\u3057\u307E\u3057\u305F\u3002\u624B\u5165\u529B\u3067\u3082\u9001\u4FE1\u3067\u304D\u307E\u3059\u3002";
      }).then(function() {
        elements.zipSearch.disabled = false;
      });
    }
    function setupTerms() {
      if (!elements.termsBox || !elements.termsAgree || !elements.termsMeter) return;
      const update = () => {
        var _a2;
        const max = elements.termsBox.scrollHeight - elements.termsBox.clientHeight;
        const pct = max <= 0 ? 100 : Math.min(100, Math.round(elements.termsBox.scrollTop / max * 100));
        elements.termsMeter.textContent = "\u898F\u7D04\u30B9\u30AF\u30ED\u30FC\u30EB\uFF1A".concat(pct, "%");
        if (pct >= 98) {
          elements.termsAgree.disabled = false;
          (_a2 = elements.termsAgreeLabel) == null ? void 0 : _a2.classList.remove("locked");
          elements.termsMeter.textContent = "\u898F\u7D04\u30B9\u30AF\u30ED\u30FC\u30EB\uFF1A\u5B8C\u4E86";
        }
      };
      elements.termsBox.addEventListener("scroll", update);
      update();
    }
    function setupPhoto() {
      var _a2, _b2, _c2;
      (_a2 = elements.cameraStart) == null ? void 0 : _a2.addEventListener("click", () => {
        var _a3;
        if (!((_a3 = navigator.mediaDevices) == null ? void 0 : _a3.getUserMedia)) {
          alert("\u3053\u306E\u7AEF\u672B\u3067\u306F\u30AB\u30E1\u30E9\u3092\u8D77\u52D5\u3067\u304D\u307E\u305B\u3093\u3002\u753B\u50CF\u30A2\u30C3\u30D7\u30ED\u30FC\u30C9\u3092\u3054\u5229\u7528\u304F\u3060\u3055\u3044\u3002");
          return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false }).then(function(stream) {
          elements.video.srcObject = stream;
        }).catch(function() {
          alert("\u30AB\u30E1\u30E9\u3092\u8D77\u52D5\u3067\u304D\u307E\u305B\u3093\u3067\u3057\u305F\u3002\u753B\u50CF\u30A2\u30C3\u30D7\u30ED\u30FC\u30C9\u3092\u3054\u5229\u7528\u304F\u3060\u3055\u3044\u3002");
        });
      });
      (_b2 = elements.capture) == null ? void 0 : _b2.addEventListener("click", () => {
        var _a3;
        if (!((_a3 = elements.video) == null ? void 0 : _a3.videoWidth)) {
          alert("\u5148\u306B\u30AB\u30E1\u30E9\u3092\u8D77\u52D5\u3057\u3066\u304F\u3060\u3055\u3044\u3002");
          return;
        }
        elements.canvas.width = elements.video.videoWidth;
        elements.canvas.height = elements.video.videoHeight;
        const ctx = elements.canvas.getContext("2d");
        ctx.drawImage(elements.video, 0, 0, elements.canvas.width, elements.canvas.height);
        const dataUrl = elements.canvas.toDataURL("image/jpeg", 0.86);
        elements.photoData.value = dataUrl;
        elements.photoUpload.value = "";
        elements.photoPreview.innerHTML = '<img alt="\u64AE\u5F71\u3057\u305F\u9854\u5199\u771F" src="'.concat(dataUrl, '">');
        elements.photoPreview.classList.remove("error");
        buildReview();
      });
      (_c2 = elements.photoUpload) == null ? void 0 : _c2.addEventListener("change", () => {
        var _a3;
        const file = (_a3 = elements.photoUpload.files) == null ? void 0 : _a3[0];
        if (!file) return;
        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > 5 * 1024 * 1024) {
          alert("JPEG\u3001PNG\u3001WebP\u5F62\u5F0F\u30675MB\u4EE5\u5185\u306E\u753B\u50CF\u3092\u9078\u629E\u3057\u3066\u304F\u3060\u3055\u3044\u3002");
          elements.photoUpload.value = "";
          return;
        }
        const reader = new FileReader();
        reader.onload = () => {
          const result = String(reader.result || "");
          elements.photoData.value = "";
          elements.photoPreview.innerHTML = '<img alt="\u30A2\u30C3\u30D7\u30ED\u30FC\u30C9\u3057\u305F\u9854\u5199\u771F" src="'.concat(result, '">');
          buildReview();
        };
        reader.readAsDataURL(file);
      });
    }
    function cssEscape(value) {
      var _a2;
      return ((_a2 = window.CSS) == null ? void 0 : _a2.escape) ? window.CSS.escape(value) : String(value).replace(/"/g, '\\"');
    }
    function escapeHtml(value) {
      return String(value != null ? value : "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
    [elements.startDate, $("#procedureDate1"), $("#procedureDate2"), $("#procedureDate3")].forEach((input) => {
      if (input) input.min = SELECTABLE_START_DATE;
    });
    form.addEventListener("input", () => {
      updatePrice();
      updateAge();
    });
    form.addEventListener("change", (event) => {
      var _a2, _b2;
      if (["birthYear", "birthMonth", "birthDay"].includes((_a2 = event.target) == null ? void 0 : _a2.id)) {
        syncBirthValue();
      }
      for (let i = 1; i <= 3; i += 1) {
        if (((_b2 = event.target) == null ? void 0 : _b2.id) === "procedureDate".concat(i)) updateProcedureSlots(i);
      }
      updatePrice();
      updateAge();
    });
    (_a = elements.nextStep) == null ? void 0 : _a.addEventListener("click", () => {
      const result = validateScope(steps[currentStep], { requirePhoto: currentStep === 4 });
      if (!result.valid) {
        showTopError(result.messages, result.invalids);
        return;
      }
      showStep(currentStep + 1);
    });
    (_b = elements.prevStep) == null ? void 0 : _b.addEventListener("click", () => showStep(currentStep - 1));
    (_c = elements.mobileSummaryToggle) == null ? void 0 : _c.addEventListener("click", () => {
      var _a2;
      return (_a2 = elements.mobileSummary) == null ? void 0 : _a2.classList.toggle("open");
    });
    (_d = elements.zipSearch) == null ? void 0 : _d.addEventListener("click", searchAddress);
    (_e = elements.postalCode) == null ? void 0 : _e.addEventListener("input", () => {
      const digits = elements.postalCode.value.replace(/[^\d]/g, "");
      if (digits.length === 7) searchAddress();
    });
    form.addEventListener("submit", (event) => {
      if (submitting) {
        event.preventDefault();
        return;
      }
      submitting = true;
      const messages = [];
      const invalids = [];
      steps.forEach((step, index) => {
        const result = validateScope(step, { requirePhoto: index === 4 });
        messages.push(...result.messages);
        invalids.push(...result.invalids);
      });
      const uniqueMessages = [...new Set(messages)];
      if (uniqueMessages.length > 0) {
        event.preventDefault();
        submitting = false;
        const firstStep = steps.findIndex((step) => invalids.some((el) => step.contains(el)));
        if (firstStep >= 0) showStep(firstStep);
        showTopError(uniqueMessages, invalids);
        return;
      }
      elements.submitButton.disabled = true;
    });
    for (let i = 1; i <= 3; i += 1) updateProcedureSlots(i);
    syncBirthValue();
    setupTerms();
    setupPhoto();
    updateAge();
    updatePrice();
    showStep(0);
  })();
})();
