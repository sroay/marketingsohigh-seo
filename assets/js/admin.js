jQuery(function($){
    // Tab switching
    $('.msh-tab').on('click',function(){
        var tab=$(this).data('tab');
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
        $(this).closest('.msh-metabox').find('.msh-tab-content').removeClass('active');
        $(this).closest('.msh-metabox').find('.msh-tab-content[data-tab="'+tab+'"]').addClass('active');
    });

    // Character counter
    $('[data-target]').each(function(){
        var $counter=$(this);
        var target=$counter.data('target');
        var max=$counter.data('max');
        var $input=$('#'+target);
        function update(){
            var len=$input.val().length;
            var color=len>max?'#d63031':(len>max*0.8?'#fdcb6e':'#00b894');
            $counter.html('<span style="color:'+color+'">'+len+'/'+max+'</span>');
        }
        $input.on('input',update);
        update();
    });

    // SERP preview live update
    $('#msh_title').on('input',function(){ $('.msh-serp-title').text($(this).val()||$('input[name=post_title]').val()); });
    $('#msh_description').on('input',function(){ $('.msh-serp-desc').text($(this).val()); });

    // SEO Score analysis
    $('#msh-analyze-btn').on('click',function(){
        var $btn=$(this);
        $btn.prop('disabled',true).text('Analyzing...');

        // Save current fields first
        var postId=$('#post_ID').val();
        $.post(mshAdmin.ajaxUrl,{
            action:'msh_calculate_score',
            nonce:mshAdmin.nonce,
            post_id:postId
        },function(res){
            $btn.prop('disabled',false).text('Analyze SEO');
            if(!res.success)return;

            var data=res.data;
            var $panel=$('#msh-score-results');
            var scoreClass=data.score>=80?'msh-score-good':(data.score>=50?'msh-score-ok':'msh-score-poor');
            var html='<div class="msh-score-badge '+scoreClass+'" style="display:inline-block;margin:8px 0">'+data.score+'/100</div>';

            $.each(data.tests,function(key,test){
                html+='<div class="msh-test msh-test-'+test.status+'">'+test.label+'</div>';
            });

            $panel.html(html);

            // Update badge in tabs
            $('.msh-score-badge').not('#msh-score-results .msh-score-badge').remove();
            $('.msh-tabs').append('<span class="msh-score-badge '+scoreClass+'">'+data.score+'/100</span>');
        });
    });
});
