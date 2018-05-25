<?php

namespace H5Test;

class H5REST extends XRuleService implements XService         //@REST_RULE: /v1/h5/$method
{
    /**
     * @api {post} /h5/detail h5服务页说明
     * @apiName  h5_detail
     * @apiGroup h5
     * @apiUse   APICommon
     * @apiParam {String} scode     服务编码
     * @apiParam {String} city      城市
     * @apiSampleRequest  https://api-cust-demo.ayibang.com/v1/h5/detail
     */
    public function detail($xcontext, $request, $response)
    {
        $city  = $request->city;
        $scode = $request->scode;
        Contract::notNull($city, "城市为空");
        Contract::notNull($scode, "服务编码为空");
        $confName = "html_info_conf/h5_detail/" . $scode;
        $plato    = PlatoClient::stdSvc("ayi_svcs");
        $ret      = $plato->getEnvConf($confName)[$confName];
        unset($ret["__ver"]);
        unset($ret["__subs"]);
        $data = [];
        foreach ($ret as $k => $blocks) {
            $tmp["key"] = $k;
            $blockKV    = [];
            foreach ($blocks as $block) {
                foreach ($block as $key => $value) {
                    $tmpKV["key"]   = $key;
                    $tmpKV["value"] = $value;
                    $blockKV[]      = $tmpKV;
                }
            }
            $tmp["value"] = $blockKV;
            $data[]       = $tmp;
        }
        $response->success($data);
    }

    /**
     * @api {post} /h5/qa h5服务页常见问题说明
     * @apiName  h5_qa
     * @apiGroup h5
     * @apiUse   APICommon
     * @apiParam {String} scode     服务编码
     * @apiParam {String} city      城市
     * @apiSampleRequest  https://api-cust-demo.ayibang.com/v1/h5/qa
     */
    public function qa($xcontext, $request, $response)
    {
        $city  = $request->city;
        $scode = $request->scode;
        Contract::notNull($city, "城市为空");
        Contract::notNull($scode, "服务编码为空");
        $confName = "html_info_conf/h5_qa/" . $scode;
        $plato    = PlatoClient::stdSvc("ayi_svcs");
        $ret      = $plato->getEnvConf($confName)[$confName];
        unset($ret["__ver"]);
        unset($ret["__subs"]);
        $data = [];
        foreach ($ret as $k => $blocks) {
            $tmp["key"] = $k;
            $blockKV    = [];
            foreach ($blocks as $block) {
                foreach ($block as $key => $value) {
                    $tmpKV["key"]   = $key;
                    $tmpKV["value"] = $value;
                    $blockKV[]      = $tmpKV;
                }
            }
            $tmp["value"] = $blockKV;
            $data[]       = $tmp;
        }
        $response->success($data);
    }
}

