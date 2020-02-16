
$(function() {
    var title = ""; 
    var seed1 = $(".hidden-values").data("seed1"); 
    var lang1 = $(".hidden-values").data("lang1");
    var lang2 = $(".hidden-values").data("lang2"); 
    var search_lang = "";
    var search_lang_title = "";

    function clear_page() {
        $(".lang-step1").removeClass("hide-step");
        $(".lang-step2").addClass("hide-step");
        $(".lang-step3").addClass("hide-step"); 
    
        $(".lang1-header").empty();
        $(".lang1-title").empty();
        $(".both-header").empty();
        $(".lang2-header").empty();
        $(".lang2-title").empty();

        $(".errors-block").empty(); 
        $(".errors-block").addClass("hide-step");

        title = $(".choose-article").val("");

        $(".choose-article-loading").addClass("hide-step"); 
        $(".compare-article-loading").addClass("hide-step"); 
    }

    $(".choose-article-form").submit(function(e) {
        console.log("choose article");
        e.preventDefault();

        title = $(".choose-article").val(); 
        search_lang = $(".choose-article-lang").val();
        search_lang_title = $(".choose-article-lang [value='" + search_lang + "']").text();

        if (title) {
            $(".choose-article-loading .article-title").text(title);
            $(".choose-article-loading .lang-title").text(search_lang_title); 
            $(".choose-article-loading").removeClass("hide-step");

            $.ajax({
                dataType: "json", 
                url: "./get_languages_article.php",
                data: {
                    "title" : title,
                    "search_lang" : search_lang
                },
                headers: {
                    "Accept-Language" : "*"
                },
                success: function(data) {
                    $(".lang-step1").addClass("hide-step");
                    $(".lang-step2").addClass("hide-step");
                    $(".lang-step3").addClass("hide-step"); 

                    seed1 = data.normalized;
                    $(".chosen-article-title").text(seed1); 
                    $(".chosen-article-lang").text(search_lang_title); 

                    $(".compare-lang1").empty();
                    $(".compare-lang2").empty();

                    if (data.result_langs && data.result_langs.length > 0) {
                        $(".lang-step2").removeClass("hide-step");
                        function MakeDropDownValues(ls, n) {
                            var listTemplate = "<option value=\"\" selected>-- Language " + n + " --</option>";
                            listTemplate = listTemplate + "<option value=\"" + search_lang + "\">" + search_lang_title + "</option>";
                            listTemplate = listTemplate + ls.map(function(i) { 
                                return "<option value=\"" + i.code + "\">" + i.title + " (" + i.l2_title + ")</option>";
                            }).join(""); 
                            return listTemplate; 
                        }
                        $(".compare-lang1").html(MakeDropDownValues(data.result_langs, "1"));
                        $(".compare-lang2").html(MakeDropDownValues(data.result_langs, "2"));
                    } else {
                        $(".errors-block").removeClass("hide-step");
                        $(".errors-block").html("Could not find any articles for <strong>" + title 
                                + "</strong> in the language <strong>" + search_lang_title + "</strong>");
                    }
                }
            }); 
        }
    });

    function compare_langs_load() {
        console.log("compare langs load");

        if (seed1 && seed1.length > 1 && lang1 && lang1.length >= 2 && lang2 && lang2.length >= 2) {
            
            $(".compare-article-loading").removeClass("hide-step"); 
            $(".lang-step3").removeClass("hide-step"); 

            $.ajax({
                dataType: "json",
                url: "./compare_article_links.php",
                data: {
                    "lang1" : encodeURI(lang1),
                    "lang2" : encodeURI(lang2),
                    "seed1" : encodeURI(seed1)
                },
                headers: {
                    "Accept-Language" : "*"
                },
                success: function(data) {
                    $(".lang-step1").addClass("hide-step");
                    $(".lang-step2").addClass("hide-step");
                    $(".lang-step3").removeClass("hide-step"); 
                    $(".compare-article-loading").addClass("hide-step");

                    $(".lang-content.hide-step").removeClass("hide-step"); 

                    var lang1_titles = data.lang1_retranslate_only
                    var lang2_titles = data.lang2_retranslate_only
                    var both_titles = data.both_langs_retranslate
                    
                    var linksTitleText = "Go to this wikipedia page"; 

                    function MakeList(ls, containerClass, lang) {
                        var listTemplate = "<div class=\"" + containerClass + "\">";
                        listTemplate = listTemplate + ls.map(function(i) { 
                            var link = "https://" + lang + ".wikipedia.org/wiki/" + i;
                            return "<a href=\"" + link + "\" class=\"wiki-link\" title=\"" + 
                                linksTitleText + "\" target=\"_blank\">" + i + "</a>";
                        }).join("");
                        listTemplate = listTemplate + "</div>"; 
                        return listTemplate; 
                    }
    
                    function MakeListPairs(ls, containerClass, lang1, lang2) { 
                        var listTemplate = "<div class=\"" + containerClass + "\">";
                        listTemplate = listTemplate + ls.map(function(i) { 
                            var link1 = "https://" + lang1 + ".wikipedia.org/wiki/" + i["t1"];
                            var link2 = "https://" + lang2 + ".wikipedia.org/wiki/" + i["t2"];
                            return "<div class=\"both-list-container\"><a href=\"" + link1 + 
                                "\" class=\"wiki-link first\">" + i["t1"] + "</a>" +
                                "<a href=\"" + link2 + "\" class=\"wiki-link second\" title=\"" + 
                                linksTitleText + "\" target=\"_blank\">" + i["t2"] + "</a></div>";
                        }).join("");
                        listTemplate = listTemplate + "</div>"; 
                        return listTemplate; 
                    }
    
                    var headerLink1 = "https://" + lang1 + ".wikipedia.org/wiki/" + data.seed1;
                    var headerLink2 = "https://" + lang2 + ".wikipedia.org/wiki/" + data.seed2;

                    $(".lang1-header").html("<a href=\"" + headerLink1 + "\" title=\"" + linksTitleText + "\" target=\"_blank\">" + data.seed1 + "</a>");
                    $(".lang1-title").text(data.seed1_langname);
                    $(".lang1-countlabel").text(lang1_titles.length + " Article Link" + (lang1_titles.length == 1 ? "" : "s"));

                    $(".both-header").text("Links in both");
                    $(".both-countlabel").text(both_titles.length + " Article Link" + (both_titles.length == 1 ? "" : "s"));

                    $(".lang2-header").html("<a href=\"" + headerLink2 + "\" title=\"" + linksTitleText + "\" target=\"_blank\">" + data.seed2 + "</a>"); 
                    $(".lang2-title").text(data.seed2_langname);
                    $(".lang2-countlabel").text(lang2_titles.length + " Article Link" + (lang2_titles.length == 1 ? "" : "s"));
    
                    $(".lang1-content").html(MakeList(lang1_titles, "lang1-list", lang1));
                    $(".both-content").html(MakeListPairs(both_titles, "both-list", lang1, lang2));
                    $(".lang2-content").html(MakeList(lang2_titles, "lang2-list", lang2));
                }
            });
        }
    } 

    $(".compare-article-links-form").submit(function(e) {
        console.log("compare article links");
        e.preventDefault(); 

        lang1 = $(".compare-lang1").val();
        lang2 = $(".compare-lang2").val(); 

        console.log("Submiting compare article links form!"); 
        
        $(".hidden-values-pass .lang1").val(lang1);
        $(".hidden-values-pass .lang2").val(lang2);
        $(".hidden-values-pass .seed1").val(seed1);
        $(".hidden-values-pass").submit(); 
    });  

    $(".about-page-link").click(function() {
        $(".about-page").toggleClass("hide-step"); 
    });

    $(".about-page-close").click(function() {
        $(".about-page").addClass("hide-step"); 
    });

    if (seed1 && seed1.length > 1 && lang1 && lang1.length >= 2 && lang2 && lang2.length >= 2) 
    {
        console.log("comparing langs started!!!"); 
        $(".lang-step1").addClass("hide-step");
        $(".lang-step1").hide();
        $(".lang-step2").hide();

        compare_langs_load();
    }
    else 
    {
        clear_page(); 
    }

});

